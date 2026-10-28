<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

/**
 * Guards the panel shell itself: Arabic RTL rendering, authentication and the
 * department isolation that keeps each team out of the other team's screens.
 */
class PanelTest extends TestCase
{
    use CreatesDomainData;
    use RefreshDatabase;

    public function test_panel_serves_arabic_right_to_left_layout(): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk();
        $response->assertSee('dir="rtl"', false);
        $response->assertSee('lang="ar"', false);
        $response->assertSee('Cible');
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_the_site_root_opens_the_panel(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }

    public function test_inactive_accounts_cannot_enter_the_panel(): void
    {
        $user = $this->manager();
        $user->forceFill(['is_active' => false])->save();

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_rental_employee_sees_rental_customers_only(): void
    {
        $employee = $this->employee(Department::Rental);

        $this->actingAs($employee)->get('/admin/rental/customers')->assertOk();

        $this->actingAs($employee)->get('/admin/resale/customers')->assertForbidden();
    }

    public function test_resale_employee_sees_resale_customers_only(): void
    {
        $employee = $this->employee(Department::Resale);

        $this->actingAs($employee)->get('/admin/resale/customers')->assertOk();

        $this->actingAs($employee)->get('/admin/rental/customers')->assertForbidden();
    }

    public function test_manager_reaches_both_departments(): void
    {
        $manager = $this->manager();

        $this->actingAs($manager)->get('/admin/rental/customers')->assertOk();
        $this->actingAs($manager)->get('/admin/resale/customers')->assertOk();
    }

    public function test_customer_list_renders_arabic_columns_and_records(): void
    {
        $employee = $this->employee(Department::Rental);
        $customer = $this->customerClass(Department::Rental)::create([
            'name' => 'سلمى عبد الرحمن',
            'phone' => '+201112345678',
        ]);

        $response = $this->actingAs($employee)->get('/admin/rental/customers');

        $response->assertOk();
        $response->assertSee('سلمى عبد الرحمن');
        $response->assertSee('الهاتف');
    }

    public function test_employee_can_open_the_create_customer_form(): void
    {
        $employee = $this->employee(Department::Rental);

        $response = $this->actingAs($employee)->get('/admin/rental/customers/create');

        $response->assertOk();
        $response->assertSee('رقم الهاتف');
    }

    public function test_employee_cannot_open_the_other_department_form(): void
    {
        $employee = $this->employee(Department::Rental);

        $this->actingAs($employee)->get('/admin/resale/customers/create')->assertForbidden();
    }

    public function test_multi_department_employee_sees_both_lists(): void
    {
        $employee = $this->employee(Department::Rental, Department::Resale);

        $this->actingAs($employee)->get('/admin/rental/customers')->assertOk();
        $this->actingAs($employee)->get('/admin/resale/customers')->assertOk();
    }

    public function test_dashboard_loads_for_an_employee(): void
    {
        $employee = $this->employee(Department::Rental);

        $this->actingAs($employee)->get('/admin')->assertOk();
    }

    public function test_inactive_manager_is_not_a_valid_panel_user(): void
    {
        $manager = $this->manager();
        $manager->forceFill(['is_active' => false])->save();

        $this->assertFalse($manager->canAccessPanel(filament()->getPanel('admin')));
        $this->assertTrue(User::factory()->manager()->create()->canAccessPanel(filament()->getPanel('admin')));
    }
}
