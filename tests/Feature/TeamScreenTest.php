<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Enums\Role;
use App\Filament\Resources\Team\Pages\CreateUser;
use App\Filament\Resources\Team\Pages\EditUser;
use App\Filament\Resources\Team\Pages\ListUsers;
use App\Models\User;
use App\Services\UserAccountService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

/**
 * The team screen is the manager's tool: it creates both roles, moves people
 * between departments, and ends access by deactivation rather than deletion
 * (D-071, D-080). Every rule is enforced by UserAccountService first.
 */
class TeamScreenTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    public function test_the_manager_sees_every_account_and_an_employee_is_refused(): void
    {
        $manager = $this->manager();
        $employee = $this->employee(Department::Rental);

        $this->actingAs($manager)
            ->get('/admin/team')
            ->assertOk()
            ->assertSee($manager->name)
            ->assertSee($employee->name);

        $this->actingAs($employee)
            ->get('/admin/team')
            ->assertForbidden();
    }

    public function test_the_manager_creates_an_employee_with_its_departments_and_area_grant(): void
    {
        $manager = $this->manager();
        $this->actingAs($manager);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'سارة عبد الله',
                'email' => 'Sara@Cible.test',
                'job_title' => 'مسؤولة تأجير',
                'role' => Role::Employee->value,
                'departments' => [Department::Rental->value, Department::Resale->value],
                'can_manage_areas' => true,
                'password' => 'temporary-pass-2026',
                'password_confirmation' => 'temporary-pass-2026',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $employee = User::query()->where('email', 'sara@cible.test')->firstOrFail();

        $this->assertTrue($employee->isEmployee());
        $this->assertTrue(Hash::check('temporary-pass-2026', $employee->password));
        $this->assertSame(
            [Department::Rental, Department::Resale],
            $employee->memberships->pluck('department')->all(),
        );
        $this->assertTrue($employee->canManageAreas());
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'user_created',
            'auditable_id' => $employee->getKey(),
            'actor_user_id' => $manager->getKey(),
        ]);
    }

    public function test_a_manager_is_created_without_department_memberships(): void
    {
        $this->actingAs($this->manager());

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'أحمد فتحي',
                'email' => 'ahmed@cible.test',
                'role' => Role::Manager->value,
                'password' => 'manager-pass-2026',
                'password_confirmation' => 'manager-pass-2026',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $manager = User::query()->where('email', 'ahmed@cible.test')->firstOrFail();

        $this->assertTrue($manager->isManager());
        $this->assertSame(0, $manager->memberships()->count());
    }

    public function test_editing_an_employee_moves_them_between_departments(): void
    {
        $employee = $this->employee(Department::Rental);
        $this->actingAs($this->manager());

        Livewire::test(EditUser::class, ['record' => $employee->getKey()])
            ->assertFormSet(['departments' => [Department::Rental->value]])
            ->fillForm([
                'departments' => [Department::Resale->value],
                'can_manage_areas' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $employee->refresh();

        $this->assertSame([Department::Resale], $employee->memberships->pluck('department')->all());
        $this->assertTrue($employee->canManageAreas());
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'user_updated',
            'auditable_id' => $employee->getKey(),
        ]);
    }

    public function test_the_manager_cannot_demote_himself_from_the_form(): void
    {
        $manager = $this->manager();
        $this->actingAs($manager);

        Livewire::test(EditUser::class, ['record' => $manager->getKey()])
            ->fillForm([
                'role' => Role::Employee->value,
                'departments' => [Department::Rental->value],
            ])
            ->call('save')
            ->assertHasFormErrors(['role']);

        $this->assertTrue($manager->fresh()->isManager());
    }

    public function test_the_manager_cannot_deactivate_himself(): void
    {
        $manager = $this->manager();

        $this->expectException(ValidationException::class);

        app(UserAccountService::class)->setActive($manager, false, $manager);
    }

    public function test_deactivating_an_account_ends_its_panel_access(): void
    {
        $employee = $this->employee(Department::Rental);
        $this->actingAs($this->manager());

        Livewire::test(ListUsers::class)
            ->callAction(TestAction::make('deactivate')->table($employee))
            ->assertHasNoActionErrors();

        $this->assertFalse($employee->fresh()->is_active);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'user_deactivated',
            'auditable_id' => $employee->getKey(),
        ]);

        $this->actingAs($employee->fresh())->get('/admin')->assertForbidden();
    }

    public function test_an_account_cannot_take_an_email_that_is_already_used(): void
    {
        $existing = $this->employee(Department::Rental);
        $this->actingAs($this->manager());

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'حساب مكرر',
                'email' => strtoupper($existing->email),
                'role' => Role::Employee->value,
                'departments' => [Department::Rental->value],
                'password' => 'duplicate-pass-2026',
                'password_confirmation' => 'duplicate-pass-2026',
            ])
            ->call('create')
            ->assertHasFormErrors(['email']);

        $this->assertSame(1, User::query()->where('email', $existing->email)->count());
    }
}
