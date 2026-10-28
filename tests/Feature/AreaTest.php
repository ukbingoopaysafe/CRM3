<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Enums\Governorate;
use App\Models\Area;
use App\Services\AreaService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class AreaTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    public function test_manager_adds_area_and_duplicate_name_is_rejected(): void
    {
        $service = app(AreaService::class);
        $manager = $this->manager();

        $service->create(['name' => 'المعادي', 'governorate' => Governorate::Cairo], $manager);

        $this->expectException(ValidationException::class);
        $service->create(['name' => 'المعادي ', 'governorate' => Governorate::Cairo], $manager);
    }

    public function test_same_area_name_is_allowed_in_another_governorate(): void
    {
        $service = app(AreaService::class);
        $manager = $this->manager();

        $service->create(['name' => 'الجيزة', 'governorate' => Governorate::Giza], $manager);
        $service->create(['name' => 'الجيزة', 'governorate' => Governorate::Cairo], $manager);

        $this->assertSame(2, Area::query()->count());
    }

    public function test_archived_area_is_hidden_from_new_request_choices_but_kept_on_history(): void
    {
        $service = app(AreaService::class);
        $manager = $this->manager();
        $area = $service->create(['name' => 'الزمالك', 'governorate' => Governorate::Cairo], $manager);
        $request = $this->makeRequest(Department::Rental, ['area_id' => $area->getKey(), 'governorate' => 'cairo']);

        $service->archive($area, $manager);

        $this->assertSame(
            0,
            $service->selectableFor(Governorate::Cairo)->where('id', $area->getKey())->count()
        );
        $this->assertSame($area->getKey(), $request->fresh()->area_id);
    }

    public function test_database_rejects_a_duplicate_area_name_inside_a_governorate(): void
    {
        $this->area(Governorate::Cairo, 'حدائق الأهرام');

        $this->expectException(QueryException::class);

        Area::create(['name' => 'حدائق الأهرام', 'governorate' => Governorate::Cairo]);
    }
}
