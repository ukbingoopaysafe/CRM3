<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Enums\Governorate;
use App\Enums\Role;
use App\Models\Area;
use App\Models\Rental\LeadSource as RentalLeadSource;
use App\Models\Resale\LeadSource as ResaleLeadSource;
use App\Models\User;
use Database\Seeders\AreaSeeder;
use Database\Seeders\FirstManagerSeeder;
use Database\Seeders\LeadSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The reference data a fresh install receives: the approved areas (D-042),
 * the approved lead sources (D-051) and the first manager account (D-063).
 */
class ReferenceDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_approved_area_list_is_seeded_for_each_governorate(): void
    {
        $this->seed(AreaSeeder::class);

        $this->assertSame(15, Area::query()->count());
        $this->assertSame(5, Area::query()->forGovernorate(Governorate::Cairo)->count());
        $this->assertSame(10, Area::query()->forGovernorate(Governorate::Giza)->count());
        $this->assertSame(15, Area::query()->selectable()->count());
        $this->assertTrue(Area::query()->where('name', 'الجيزة')->exists());
    }

    public function test_seeding_areas_twice_does_not_duplicate_them(): void
    {
        $this->seed(AreaSeeder::class);
        $this->seed(AreaSeeder::class);

        $this->assertSame(15, Area::query()->count());
    }

    public function test_each_department_receives_the_approved_lead_sources(): void
    {
        $this->seed(LeadSourceSeeder::class);

        foreach ([RentalLeadSource::class, ResaleLeadSource::class] as $model) {
            $this->assertSame(9, $model::query()->count());
            $this->assertSame(9, $model::query()->active()->count());
            $this->assertSame(
                [1, 2, 3, 4, 5, 6, 7, 8, 9],
                $model::query()->orderBy('sort_order')->pluck('sort_order')->all(),
            );
        }
    }

    public function test_only_the_other_lead_source_requires_a_description(): void
    {
        $this->seed(LeadSourceSeeder::class);

        foreach ([RentalLeadSource::class, ResaleLeadSource::class] as $model) {
            $this->assertSame(
                ['أخرى'],
                $model::query()->where('requires_description', true)->pluck('name')->all(),
            );
        }
    }

    public function test_the_first_manager_is_created_once_and_keeps_the_configured_password(): void
    {
        config(['crm.first_manager.email' => 'owner@example.test']);
        config(['crm.first_manager.password' => 'first-login-password']);

        $this->seed(FirstManagerSeeder::class);

        $manager = User::query()->sole();

        $this->assertSame('owner@example.test', $manager->email);
        $this->assertSame(Role::Manager, $manager->role);
        $this->assertTrue($manager->isActive());
        $this->assertSame(Department::cases(), $manager->departmentScope());
        $this->assertTrue(Hash::check('first-login-password', $manager->password));
    }

    public function test_a_second_run_does_not_replace_an_existing_manager(): void
    {
        config(['crm.first_manager.password' => 'first-login-password']);

        $this->seed(FirstManagerSeeder::class);
        $existing = User::query()->sole();

        config(['crm.first_manager.password' => 'another-password']);

        $this->seed(FirstManagerSeeder::class);

        $this->assertSame(1, User::query()->count());
        $this->assertTrue(Hash::check('first-login-password', $existing->refresh()->password));
    }

    public function test_the_database_seeder_creates_a_usable_install(): void
    {
        config(['crm.first_manager.password' => 'first-login-password']);

        $this->seed();

        $this->assertSame(15, Area::query()->count());
        $this->assertSame(9, RentalLeadSource::query()->count());
        $this->assertSame(9, ResaleLeadSource::query()->count());
        $this->assertSame(1, User::query()->where('role', Role::Manager->value)->count());
    }
}
