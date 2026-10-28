<?php

namespace Tests\Feature;

use App\Enums\ContactParty;
use App\Enums\Department;
use App\Enums\Governorate;
use App\Enums\Outcome;
use App\Enums\RequestStatus;
use App\Enums\Stage;
use App\Services\ActivityService;
use App\Services\RequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class RequestLifecycleTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    public function test_active_request_requires_a_next_follow_up(): void
    {
        $this->expectException(ValidationException::class);

        $this->makeRequest(Department::Rental, ['next_follow_up_at' => null]);
    }

    public function test_request_requires_a_customer_or_a_broker(): void
    {
        $this->expectException(ValidationException::class);

        $this->makeRequest(Department::Rental, ['customer_id' => null]);
    }

    public function test_area_must_belong_to_the_selected_governorate(): void
    {
        $gizaArea = $this->area(Governorate::Giza, 'الدقي');

        $this->expectException(ValidationException::class);

        $this->makeRequest(Department::Rental, [
            'area_id' => $gizaArea->getKey(),
            'governorate' => Governorate::Cairo->value,
        ]);
    }

    public function test_new_request_starts_active_in_receiving_stage_with_history(): void
    {
        $request = $this->makeRequest();

        $this->assertSame(Stage::Received, $request->stage);
        $this->assertSame(RequestStatus::Active, $request->status);
        $this->assertSame(1, $request->stageHistories()->count());
    }

    public function test_stage_changes_are_recorded_without_changing_status(): void
    {
        $request = $this->makeRequest();
        $employee = $request->owner;

        app(RequestService::class)->changeStage($request, Stage::Searching, $employee);

        $request->refresh();

        $this->assertSame(Stage::Searching, $request->stage);
        $this->assertSame(RequestStatus::Active, $request->status);
        $this->assertSame(2, $request->stageHistories()->count());
    }

    public function test_pause_keeps_stage_and_resume_returns_to_it(): void
    {
        $service = app(RequestService::class);
        $request = $this->makeRequest();
        $employee = $request->owner;

        $service->changeStage($request, Stage::Viewing, $employee);
        $service->pause($request->fresh(), $employee);
        $request->refresh();

        $this->assertSame(RequestStatus::Paused, $request->status);
        $this->assertSame(Stage::Viewing, $request->stage);

        $service->resume($request, now()->addDays(2), $employee);
        $request->refresh();

        $this->assertSame(RequestStatus::Active, $request->status);
        $this->assertSame(Stage::Viewing, $request->stage);
    }

    public function test_unsuccessful_close_requires_employee_reason_text(): void
    {
        $request = $this->makeRequest();

        $this->expectException(ValidationException::class);

        app(RequestService::class)->close($request, Outcome::Unsuccessful, $request->owner, '  ');
    }

    public function test_successful_close_does_not_create_a_deal_automatically(): void
    {
        $request = $this->makeRequest();

        app(RequestService::class)->close($request, Outcome::Successful, $request->owner);
        $request->refresh();

        $this->assertSame(RequestStatus::Closed, $request->status);
        $this->assertSame(Outcome::Successful, $request->outcome);
        $this->assertFalse($request->hasDeal());
        $this->assertNull($request->outcome_reason_text);
    }

    public function test_unsuccessful_close_stores_the_reason_text(): void
    {
        $request = $this->makeRequest();

        app(RequestService::class)->close($request, Outcome::Unsuccessful, $request->owner, 'الميزانية لا تناسب');
        $request->refresh();

        $this->assertSame('الميزانية لا تناسب', $request->outcome_reason_text);
    }

    public function test_closed_request_rejects_new_activities_and_edits(): void
    {
        $service = app(RequestService::class);
        $request = $this->makeRequest();
        $service->close($request, Outcome::Successful, $request->owner);

        $this->expectException(ValidationException::class);

        app(ActivityService::class)->record($request->fresh(), [
            'channel' => 'call',
            'result' => 'متابعة',
        ], $request->owner);
    }

    public function test_archived_customer_cannot_be_attached_to_a_new_request(): void
    {
        $customer = $this->customerClass(Department::Rental)::create([
            'name' => 'عميل مؤرشف',
            'phone' => '+201011111111',
            'archived_at' => now(),
        ]);

        $this->expectException(ValidationException::class);

        $this->makeRequest(Department::Rental, ['_customer' => $customer]);
    }

    public function test_contact_party_is_derived_from_the_only_linked_party(): void
    {
        $this->assertSame(
            ContactParty::Customer,
            $this->makeRequest()->contact_party
        );
    }
}
