<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Enums\ReassignmentStatus;
use App\Filament\Resources\Rental\Reassignments\Pages\ListReassignments;
use App\Filament\Resources\Rental\Requests\Pages\ViewRequest;
use App\Services\ReassignmentService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

/**
 * The screens must keep the decision in the manager's hands and the withdrawal
 * in the requester's hands; the service enforces both, the panel only mirrors
 * them (D-027, D-070).
 */
class ReassignmentScreenTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    public function test_the_reassignment_list_renders_for_the_manager_and_the_employee(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($this->manager())->get('/admin/rental/reassignments')->assertOk();
        $this->actingAs($request->owner)->get('/admin/rental/reassignments')->assertOk();
    }

    public function test_an_employee_starts_the_request_from_the_request_page(): void
    {
        $request = $this->makeRequest();
        $target = $this->employee(Department::Rental);
        $this->actingAs($request->owner);

        Livewire::test(ViewRequest::class, ['record' => $request->getKey()])
            ->callAction('requestReassignment', data: [
                'target_user_id' => $target->getKey(),
                'reason' => 'توزيع الأحمال داخل القسم.',
            ])
            ->assertHasNoActionErrors();

        $reassignment = $request->reassignments()->first();

        $this->assertNotNull($reassignment);
        $this->assertSame(ReassignmentStatus::Pending, $reassignment->status);
        $this->assertSame($request->owner_user_id, $request->fresh()->owner_user_id);
    }

    public function test_the_manager_approves_the_request_from_the_list_and_ownership_moves(): void
    {
        $request = $this->makeRequest();
        $target = $this->employee(Department::Rental);
        $reassignment = app(ReassignmentService::class)->request($request, $target->getKey(), 'الموظف في إجازة.', $request->owner);

        $this->actingAs($this->manager());

        Livewire::test(ListReassignments::class)
            ->callAction(TestAction::make('approve')->table($reassignment), data: ['note' => 'موافق'])
            ->assertHasNoActionErrors();

        $this->assertSame(ReassignmentStatus::Approved, $reassignment->fresh()->status);
        $this->assertSame($target->getKey(), $request->fresh()->owner_user_id);
    }

    public function test_a_pending_request_can_be_withdrawn_by_its_requester_only(): void
    {
        $request = $this->makeRequest();
        $target = $this->employee(Department::Rental);
        $reassignment = app(ReassignmentService::class)->request($request, $target->getKey(), 'إعادة توزيع.', $request->owner);

        $this->actingAs($request->owner);

        Livewire::test(ListReassignments::class)
            ->assertActionVisible(TestAction::make('cancelReassignment')->table($reassignment))
            ->assertActionHidden(TestAction::make('approve')->table($reassignment));

        $this->actingAs($this->manager());

        Livewire::test(ListReassignments::class)
            ->assertActionHidden(TestAction::make('cancelReassignment')->table($reassignment));
    }

    public function test_a_reassignment_of_the_other_department_is_not_reachable(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($this->employee(Department::Resale))
            ->get('/admin/rental/reassignments')
            ->assertForbidden();
    }
}
