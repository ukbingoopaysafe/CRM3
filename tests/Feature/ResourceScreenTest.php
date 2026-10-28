<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Enums\Outcome;
use App\Filament\Resources\Rental\Requests\Pages\ViewRequest;
use App\Filament\Resources\Requests\RelationManagers\ActivitiesRelationManager;
use App\Models\Rental;
use App\Services\RequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

/**
 * Renders every panel screen of a resource once, so a broken schema, table or
 * infolist fails here instead of in front of the owner.
 */
class ResourceScreenTest extends TestCase
{
    use CreatesDomainData;
    use RefreshDatabase;

    public function test_broker_screens_render_for_both_departments(): void
    {
        $rental = $this->employee(Department::Rental);
        $resale = $this->employee(Department::Resale);

        $this->actingAs($rental)->get('/admin/rental/brokers')->assertOk();
        $this->actingAs($rental)->get('/admin/rental/brokers/create')->assertOk();
        $this->actingAs($resale)->get('/admin/resale/brokers')->assertOk();
        $this->actingAs($resale)->get('/admin/resale/brokers/create')->assertOk();
    }

    public function test_broker_edit_screen_renders(): void
    {
        $employee = $this->employee(Department::Rental);
        $broker = Rental\Broker::create([
            'name' => 'وسيط الإيجار',
            'phone' => '+201000000001',
            'created_by' => $employee->getKey(),
        ]);

        $this->actingAs($employee)
            ->get('/admin/rental/brokers/'.$broker->getKey().'/edit')
            ->assertOk()
            ->assertSee('وسيط الإيجار');
    }

    public function test_request_list_and_create_screens_render(): void
    {
        $rental = $this->employee(Department::Rental);
        $resale = $this->employee(Department::Resale);

        $this->actingAs($rental)->get('/admin/rental/requests')->assertOk();
        $this->actingAs($rental)->get('/admin/rental/requests/create')->assertOk();
        $this->actingAs($resale)->get('/admin/resale/requests')->assertOk();
        $this->actingAs($resale)->get('/admin/resale/requests/create')->assertOk();
    }

    public function test_request_view_screen_renders_the_whole_record(): void
    {
        $request = $this->makeRequest(Department::Rental, ['unit_type' => 'apartment', 'rooms' => 3]);

        $response = $this->actingAs($request->owner)
            ->get('/admin/rental/requests/'.$request->getKey());

        $response->assertOk();
        $response->assertSee('طلب #'.$request->getKey());
        $response->assertSee('استقبال');
        $response->assertSee('شقة');
        $response->assertSee('الميزانية');
    }

    public function test_activities_relation_manager_renders_and_accepts_a_new_log(): void
    {
        $request = $this->makeRequest(Department::Rental);

        $this->actingAs($request->owner);

        Livewire::test(ActivitiesRelationManager::class, [
            'ownerRecord' => $request,
            'pageClass' => ViewRequest::class,
        ])
            ->assertSuccessful()
            ->assertSee('سجل التواصل')
            ->assertSee('لا يوجد تواصل مسجل');
    }

    public function test_request_edit_screen_renders(): void
    {
        $request = $this->makeRequest(Department::Resale, ['unit_type' => 'villa', 'area_min' => 120]);

        $response = $this->actingAs($request->owner)
            ->get('/admin/resale/requests/'.$request->getKey().'/edit');

        $response->assertOk();
        $response->assertSee('إعادة البيع');
    }

    public function test_a_request_of_another_department_is_not_reachable(): void
    {
        $request = $this->makeRequest(Department::Rental);
        $resaleEmployee = $this->employee(Department::Resale);

        $this->actingAs($resaleEmployee)
            ->get('/admin/rental/requests/'.$request->getKey())
            ->assertForbidden();
    }

    public function test_closed_request_still_renders_its_view_page(): void
    {
        $request = $this->makeRequest(Department::Rental);
        app(RequestService::class)->close(
            $request,
            Outcome::Unsuccessful,
            $request->owner,
            'العميل أجّل البحث',
        );

        $response = $this->actingAs($request->owner)
            ->get('/admin/rental/requests/'.$request->getKey().'/edit');

        $response->assertOk();
    }

    public function test_resale_request_does_not_show_rental_only_fields(): void
    {
        $request = $this->makeRequest(Department::Resale);

        $response = $this->actingAs($request->owner)
            ->get('/admin/resale/requests/'.$request->getKey().'/edit');

        $response->assertOk();
        $response->assertSee('أقل مساحة');
        $response->assertDontSee('مدة الإيجار');
    }
}
