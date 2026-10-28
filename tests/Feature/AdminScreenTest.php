<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Enums\Governorate;
use App\Filament\Pages\Dashboard;
use App\Models\DepartmentMembership;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\SummaryMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

/**
 * The administration screens: the shared area directory, the audit trail and
 * the per-department dashboard indicators.
 */
class AdminScreenTest extends TestCase
{
    use CreatesDomainData;
    use RefreshDatabase;

    public function test_only_the_manager_opens_the_area_directory(): void
    {
        $manager = $this->manager();
        $employee = $this->employee(Department::Rental);

        $this->actingAs($manager)->get('/admin/areas')->assertOk();
        $this->actingAs($employee)->get('/admin/areas')->assertForbidden();
    }

    public function test_an_employee_granted_area_management_can_add_areas(): void
    {
        $user = $this->employee(Department::Rental);
        DepartmentMembership::query()
            ->where('user_id', $user->getKey())
            ->update(['can_manage_areas' => true]);

        $this->actingAs($user->refresh())->get('/admin/areas')->assertOk();
        $this->actingAs($user)->get('/admin/areas/create')->assertOk();
    }

    public function test_an_employee_without_the_grant_cannot_add_areas(): void
    {
        $employee = $this->employee(Department::Rental);

        $this->actingAs($employee)->get('/admin/areas/create')->assertForbidden();
    }

    public function test_area_screen_lists_areas_with_their_governorate(): void
    {
        $manager = $this->manager();
        $this->area(Governorate::Giza, 'الدقي');

        $response = $this->actingAs($manager)->get('/admin/areas');

        $response->assertOk();
        $response->assertSee('الدقي');
        $response->assertSee('الجيزة');
    }

    public function test_an_employee_sees_only_their_own_audit_events(): void
    {
        $employee = $this->employee(Department::Rental);
        $colleague = $this->employee(Department::Rental);

        app(CustomerService::class)->create(
            $this->customerClass(Department::Rental),
            ['name' => 'عميل الموظف', 'phone' => '+201111111111'],
            $employee,
        );

        app(CustomerService::class)->create(
            $this->customerClass(Department::Rental),
            ['name' => 'عميل الزميل', 'phone' => '+201222222222'],
            $colleague,
        );

        $response = $this->actingAs($employee)->get('/admin/audit');

        $response->assertOk();
        $response->assertSee($employee->name);
        $response->assertDontSee($colleague->name);
    }

    public function test_a_manager_sees_the_department_trail(): void
    {
        $manager = $this->manager();
        $employee = $this->employee(Department::Rental);

        app(CustomerService::class)->create(
            $this->customerClass(Department::Rental),
            ['name' => 'عميل القسم', 'phone' => '+201333333333'],
            $employee,
        );

        $response = $this->actingAs($manager)->get('/admin/audit');

        $response->assertOk();
        $response->assertSee($employee->name);
    }

    public function test_audit_trail_cannot_be_created_from_the_panel(): void
    {
        $manager = $this->manager();

        $this->actingAs($manager)->get('/admin/audit/create')->assertNotFound();
    }

    public function test_dashboard_shows_only_the_department_of_the_user(): void
    {
        $rental = $this->employee(Department::Rental);
        $manager = $this->manager();

        $this->actingAs($rental)
            ->get('/admin')
            ->assertOk()
            ->assertSee('cb-board-rental', false)
            ->assertDontSee('cb-board-resale', false);

        $this->actingAs($manager)
            ->get('/admin')
            ->assertOk()
            ->assertSee('cb-board-rental', false)
            ->assertSee('cb-board-resale', false);
    }

    public function test_dashboard_counts_only_its_own_department_requests(): void
    {
        $rental = $this->makeRequest(Department::Rental);
        $this->makeRequest(Department::Resale);
        $this->makeRequest(Department::Resale);

        $this->actingAs($rental->owner);

        Livewire::test(Dashboard::class)
            ->assertSuccessful()
            ->assertSee('طلبات نشطة')
            ->assertSee('متابعات متأخرة');

        $this->assertSame(1, app(SummaryMetrics::class)->for(Department::Rental)['active']);
        $this->assertSame(2, app(SummaryMetrics::class)->for(Department::Resale)['active']);
    }

    public function test_admin_navigation_is_limited_to_the_manager(): void
    {
        $employee = $this->employee(Department::Rental);

        $this->actingAs($employee)->get('/admin')
            ->assertOk()
            ->assertDontSee('دليل المناطق');
    }

    public function test_inactive_user_cannot_open_admin_screens(): void
    {
        $user = $this->manager();
        $user->forceFill(['is_active' => false])->save();

        $this->actingAs($user)->get('/admin/areas')->assertForbidden();
        $this->assertFalse($user->fresh()->canAccessPanel(filament()->getPanel('admin')));
        $this->assertTrue(User::factory()->manager()->create()->isActive());
    }
}
