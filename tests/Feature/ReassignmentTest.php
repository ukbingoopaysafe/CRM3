<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Enums\Outcome;
use App\Enums\ReassignmentStatus;
use App\Services\ReassignmentService;
use App\Services\RequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class ReassignmentTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    public function test_an_approved_request_moves_the_ownership(): void
    {
        $owner = $this->employee(Department::Rental);
        $target = $this->employee(Department::Rental);
        $request = $this->makeRequest(Department::Rental, ['_owner' => $owner]);
        $service = app(ReassignmentService::class);

        $reassignment = $service->request($request, $target->getKey(), 'توزيع الأحمال', $owner);

        $this->assertSame(ReassignmentStatus::Pending, $reassignment->status);
        $this->assertSame($owner->getKey(), $request->fresh()->owner_user_id);

        $service->approve($reassignment, $this->manager());

        $this->assertSame(ReassignmentStatus::Approved, $reassignment->fresh()->status);
        $this->assertSame($target->getKey(), $request->fresh()->owner_user_id);
    }

    public function test_a_rejected_request_leaves_the_ownership_untouched(): void
    {
        $owner = $this->employee(Department::Rental);
        $target = $this->employee(Department::Rental);
        $request = $this->makeRequest(Department::Rental, ['_owner' => $owner]);
        $service = app(ReassignmentService::class);

        $reassignment = $service->request($request, $target->getKey(), 'توزيع الأحمال', $owner);
        $service->reject($reassignment, $this->manager(), 'الملف يخص الموظف الحالي');

        $this->assertSame(ReassignmentStatus::Rejected, $reassignment->fresh()->status);
        $this->assertSame($owner->getKey(), $request->fresh()->owner_user_id);
    }

    public function test_a_second_pending_request_is_blocked(): void
    {
        $owner = $this->employee(Department::Rental);
        $target = $this->employee(Department::Rental);
        $request = $this->makeRequest(Department::Rental, ['_owner' => $owner]);
        $service = app(ReassignmentService::class);

        $service->request($request, $target->getKey(), 'توزيع الأحمال', $owner);

        $this->expectException(ValidationException::class);

        $service->request($request, $target->getKey(), 'محاولة ثانية', $owner);
    }

    public function test_the_company_manager_can_never_be_a_target(): void
    {
        $owner = $this->employee(Department::Rental);
        $request = $this->makeRequest(Department::Rental, ['_owner' => $owner]);

        $this->expectException(ValidationException::class);

        app(ReassignmentService::class)->request($request, $this->manager()->getKey(), 'توزيع', $owner);
    }

    public function test_the_target_must_work_in_the_request_department(): void
    {
        $owner = $this->employee(Department::Rental);
        $outsider = $this->employee(Department::Resale);
        $request = $this->makeRequest(Department::Rental, ['_owner' => $owner]);

        $this->expectException(ValidationException::class);

        app(ReassignmentService::class)->request($request, $outsider->getKey(), 'نقل خاطئ', $owner);
    }

    public function test_only_the_manager_decides_the_request(): void
    {
        $owner = $this->employee(Department::Rental);
        $target = $this->employee(Department::Rental);
        $request = $this->makeRequest(Department::Rental, ['_owner' => $owner]);
        $service = app(ReassignmentService::class);

        $reassignment = $service->request($request, $target->getKey(), 'توزيع الأحمال', $owner);

        $this->expectException(ValidationException::class);

        $service->approve($reassignment, $owner);
    }

    public function test_only_the_requester_can_cancel_and_then_request_again(): void
    {
        $owner = $this->employee(Department::Rental);
        $target = $this->employee(Department::Rental);
        $other = $this->employee(Department::Rental);
        $request = $this->makeRequest(Department::Rental, ['_owner' => $owner]);
        $service = app(ReassignmentService::class);

        $reassignment = $service->request($request, $target->getKey(), 'توزيع الأحمال', $owner);

        try {
            $service->cancel($reassignment, $other);
            $this->fail('Only the requester may cancel a pending request.');
        } catch (ValidationException) {
            // expected
        }

        $service->cancel($reassignment, $owner);

        $this->assertSame(ReassignmentStatus::Cancelled, $reassignment->fresh()->status);
        $this->assertSame($owner->getKey(), $request->fresh()->owner_user_id);

        $service->request($request, $target->getKey(), 'طلب جديد', $owner);

        $this->assertSame(1, $request->reassignments()->where('status', ReassignmentStatus::Pending->value)->count());
    }

    public function test_a_manager_can_transfer_ownership_directly(): void
    {
        $owner = $this->employee(Department::Rental);
        $target = $this->employee(Department::Rental);
        $request = $this->makeRequest(Department::Rental, ['_owner' => $owner]);

        app(ReassignmentService::class)->direct($request, $target->getKey(), 'قرار إداري', $this->manager());

        $this->assertSame($target->getKey(), $request->fresh()->owner_user_id);
    }

    public function test_a_closed_request_cannot_be_reassigned(): void
    {
        $owner = $this->employee(Department::Rental);
        $target = $this->employee(Department::Rental);
        $request = $this->makeRequest(Department::Rental, ['_owner' => $owner]);

        app(RequestService::class)->close($request, Outcome::Unsuccessful, $owner, 'انتهت الحاجة');

        $this->expectException(ValidationException::class);

        app(ReassignmentService::class)->request($request->fresh(), $target->getKey(), 'متأخر', $owner);
    }
}
