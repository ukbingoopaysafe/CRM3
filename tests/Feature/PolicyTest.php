<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Models\Area;
use App\Models\AuditEvent;
use App\Models\DepartmentMembership;
use App\Models\User;
use App\Services\CustomerService;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class PolicyTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    public function test_an_employee_cannot_reach_a_request_of_the_other_department(): void
    {
        $employee = $this->employee(Department::Rental);
        $ownRequest = $this->makeRequest(Department::Rental);
        $resaleRequest = $this->makeRequest(Department::Resale);

        $this->assertTrue(Gate::forUser($employee)->allows('view', $ownRequest));
        $this->assertFalse(Gate::forUser($employee)->allows('view', $resaleRequest));
        $this->assertFalse(Gate::forUser($employee)->allows('update', $resaleRequest));
        $this->assertFalse(Gate::forUser($employee)->allows('close', $resaleRequest));
    }

    public function test_the_manager_reaches_both_departments_without_merging_them(): void
    {
        $manager = $this->manager();
        $rental = $this->makeRequest(Department::Rental);
        $resale = $this->makeRequest(Department::Resale);

        $this->assertTrue(Gate::forUser($manager)->allows('view', $rental));
        $this->assertTrue(Gate::forUser($manager)->allows('view', $resale));
        $this->assertTrue(Gate::forUser($manager)->allows('decideReassignment', $rental));
        $this->assertFalse(Gate::forUser($this->employee(Department::Rental))->allows('decideReassignment', $rental));
    }

    public function test_only_the_manager_archives_a_customer_record(): void
    {
        $employee = $this->employee(Department::Rental);
        $manager = $this->manager();
        $customer = app(CustomerService::class)->create(
            $this->customerClass(Department::Rental),
            ['name' => 'عميل', 'phone' => '01000000020'],
            $employee,
        );

        $this->assertFalse(Gate::forUser($employee)->allows('archive', $customer));
        $this->assertTrue(Gate::forUser($manager)->allows('archive', $customer));
        $this->assertFalse(Gate::forUser($manager)->allows('delete', $customer));
    }

    public function test_employees_see_only_their_own_audit_events(): void
    {
        $employee = $this->employee(Department::Rental);
        $other = $this->employee(Department::Rental);
        $manager = $this->manager();

        $service = app(CustomerService::class);
        $service->create($this->customerClass(Department::Rental), ['name' => 'أ', 'phone' => '01000000021'], $employee);
        $service->create($this->customerClass(Department::Rental), ['name' => 'ب', 'phone' => '01000000022'], $other);

        $own = AuditEvent::query()->where('actor_user_id', $employee->getKey())->firstOrFail();
        $foreign = AuditEvent::query()->where('actor_user_id', $other->getKey())->firstOrFail();

        $this->assertTrue(Gate::forUser($employee)->allows('view', $own));
        $this->assertFalse(Gate::forUser($employee)->allows('view', $foreign));
        $this->assertTrue(Gate::forUser($manager)->allows('view', $foreign));
        $this->assertFalse(Gate::forUser($manager)->allows('update', $foreign));
    }

    public function test_area_creation_needs_the_explicit_permission(): void
    {
        $employee = $this->employee(Department::Rental);
        $granted = $this->employee(Department::Rental);
        DepartmentMembership::query()
            ->where('user_id', $granted->getKey())
            ->update(['can_manage_areas' => true]);
        $granted->refresh()->load('memberships');

        $area = $this->area();

        $this->assertFalse(Gate::forUser($employee)->allows('create', Area::class));
        $this->assertTrue(Gate::forUser($granted)->allows('create', Area::class));
        $this->assertFalse(Gate::forUser($granted)->allows('update', $area));
        $this->assertFalse(Gate::forUser($granted)->allows('archive', $area));
        $this->assertTrue(Gate::forUser($this->manager())->allows('update', $area));
    }

    public function test_an_inactive_account_cannot_enter_the_panel(): void
    {
        $user = User::factory()->employee()->create(['is_active' => false]);

        $this->assertFalse($user->canAccessPanel(app(Panel::class)));
    }
}
