<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Account creation before the team screen exists: the command is the only
 * supported way to add staff, so it must enforce the department rules itself
 * (D-034, D-063).
 */
class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_employee_inside_the_requested_department(): void
    {
        $this->artisan('users:create', [
            'email' => 'employee@example.test',
            '--name' => 'سارة',
            '--password' => 'secret-password',
            '--department' => ['rental'],
        ])->assertSuccessful();

        $user = User::query()->sole();

        $this->assertSame('سارة', $user->name);
        $this->assertSame(Role::Employee, $user->role);
        $this->assertTrue(Hash::check('secret-password', $user->password));
        $this->assertSame([Department::Rental], $user->departmentScope());
    }

    public function test_it_creates_a_manager_who_covers_both_departments(): void
    {
        $this->artisan('users:create', [
            'email' => 'manager@example.test',
            '--password' => 'secret-password',
            '--role' => 'manager',
        ])->assertSuccessful();

        $manager = User::query()->sole();

        $this->assertSame(Role::Manager, $manager->role);
        $this->assertSame(Department::cases(), $manager->departmentScope());
        $this->assertSame(0, $manager->memberships()->count());
    }

    public function test_an_employee_without_a_department_is_rejected(): void
    {
        $this->artisan('users:create', [
            'email' => 'employee@example.test',
            '--password' => 'secret-password',
        ])->assertFailed();

        $this->assertSame(0, User::query()->count());
    }

    public function test_unknown_departments_are_rejected(): void
    {
        $this->artisan('users:create', [
            'email' => 'employee@example.test',
            '--password' => 'secret-password',
            '--department' => ['cairo'],
        ])->assertFailed();

        $this->assertSame(0, User::query()->count());
    }

    public function test_duplicate_emails_are_rejected(): void
    {
        User::factory()->employee()->create(['email' => 'employee@example.test']);

        $this->artisan('users:create', [
            'email' => 'employee@example.test',
            '--password' => 'secret-password',
            '--department' => ['resale'],
        ])->assertFailed();

        $this->assertSame(1, User::query()->count());
    }

    public function test_an_employee_can_join_both_departments(): void
    {
        $this->artisan('users:create', [
            'email' => 'both@example.test',
            '--password' => 'secret-password',
            '--department' => ['rental', 'resale'],
        ])->assertSuccessful();

        $this->assertSame(Department::cases(), User::query()->sole()->departmentScope());
    }
}
