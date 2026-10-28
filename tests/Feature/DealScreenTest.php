<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Enums\Outcome;
use App\Filament\Resources\Rental\Deals\Pages\CreateDeal;
use App\Models\Base\Request;
use App\Services\DealService;
use App\Services\RequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

/**
 * The deal screens must write through DealService: the closed/successful rule,
 * the single-deal rule and the commission lines all live there (D-058).
 */
class DealScreenTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    private function closedRequest(Department $department = Department::Rental): Request
    {
        $request = $this->makeRequest($department);
        app(RequestService::class)->close($request, Outcome::Successful, $request->owner);

        return $request->fresh();
    }

    public function test_deal_screens_render_for_both_departments(): void
    {
        $rental = $this->employee(Department::Rental);
        $resale = $this->employee(Department::Resale);

        $this->actingAs($rental)->get('/admin/rental/deals')->assertOk();
        $this->actingAs($rental)->get('/admin/rental/deals/create')->assertOk();
        $this->actingAs($resale)->get('/admin/resale/deals')->assertOk();
        $this->actingAs($resale)->get('/admin/resale/deals/create')->assertOk();
    }

    public function test_the_create_screen_preselects_the_request_from_the_url(): void
    {
        $request = $this->closedRequest();

        $response = $this->actingAs($request->owner)
            ->get('/admin/rental/deals/create?request_id='.$request->getKey());

        $response->assertOk();
        $response->assertSee('تسجيل صفقة');
        $response->assertSee('بنود العمولة');
    }

    public function test_the_panel_writes_the_deal_and_its_commission_through_the_service(): void
    {
        $request = $this->closedRequest();
        $this->actingAs($request->owner);

        Livewire::test(CreateDeal::class)
            ->fillForm([
                'request_id' => $request->getKey(),
                'deal_date' => now()->toDateString(),
                'agreed_total_value' => 60000,
                'currency' => 'EGP',
                'rental_term' => 'annual',
                'rental_term_count' => 1,
                'commissions' => [[
                    'payer' => 'المالك',
                    'beneficiary' => 'Cible',
                    'method' => 'percentage',
                    'value' => 2.5,
                ]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $deal = $request->fresh()->deal;

        $this->assertNotNull($deal);
        $this->assertSame('monthly', $deal->payment_schedule);
        $this->assertSame(1, $deal->commissions()->count());
        $this->assertNotNull($request->customer->aftercare()->first());
    }

    public function test_a_request_without_a_successful_close_is_rejected_by_the_service(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($request->owner);

        Livewire::test(CreateDeal::class)
            ->fillForm([
                'request_id' => $request->getKey(),
                'deal_date' => now()->toDateString(),
                'agreed_total_value' => 5000,
                'currency' => 'EGP',
            ])
            ->call('create');

        $this->assertNull($request->fresh()->deal);
    }

    public function test_the_deal_view_screen_renders_the_agreement(): void
    {
        $request = $this->closedRequest();
        app(DealService::class)->create($request, [
            'agreed_total_value' => 45000,
            'deal_date' => now()->toDateString(),
            'unit_code' => 'R-120',
            'commissions' => [[
                'payer' => 'المالك',
                'beneficiary' => 'Cible',
                'method' => 'fixed',
                'value' => 1500,
            ]],
        ], $request->owner);

        $deal = $request->fresh()->deal;

        $response = $this->actingAs($request->owner)
            ->get('/admin/rental/deals/'.$deal->getKey());

        $response->assertOk();
        $response->assertSee('صفقة #'.$deal->getKey());
        $response->assertSee('45,000.00');
        $response->assertSee('R-120');
        $response->assertSee('Cible');
    }

    public function test_a_deal_of_the_other_department_is_not_reachable(): void
    {
        $request = $this->closedRequest();
        app(DealService::class)->create($request, [
            'agreed_total_value' => 30000,
            'deal_date' => now()->toDateString(),
        ], $request->owner);

        $deal = $request->fresh()->deal;
        $resaleEmployee = $this->employee(Department::Resale);

        $this->actingAs($resaleEmployee)
            ->get('/admin/rental/deals/'.$deal->getKey())
            ->assertForbidden();
    }
}
