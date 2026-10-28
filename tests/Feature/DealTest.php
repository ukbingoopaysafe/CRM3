<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Enums\Outcome;
use App\Enums\RentalTerm;
use App\Models\Base\Request;
use App\Models\User;
use App\Services\AftercareService;
use App\Services\DealService;
use App\Services\RequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class DealTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    /**
     * @return array{0: Request, 1: User}
     */
    private function closedRequest(Department $department = Department::Rental): array
    {
        $request = $this->makeRequest($department);
        app(RequestService::class)->close($request, Outcome::Successful, $request->owner);

        return [$request->fresh(), $request->owner];
    }

    public function test_a_deal_cannot_be_created_before_a_successful_close(): void
    {
        $request = $this->makeRequest();

        $this->expectException(ValidationException::class);

        app(DealService::class)->create($request, [
            'agreed_total_value' => 15000,
            'deal_date' => now()->toDateString(),
        ], $request->owner);
    }

    public function test_an_annual_rental_is_paid_monthly_over_the_full_term(): void
    {
        [$request, $owner] = $this->closedRequest();

        $deal = app(DealService::class)->create($request, [
            'agreed_total_value' => 60000,
            'deal_date' => now()->toDateString(),
            'rental_term' => RentalTerm::Annual->value,
            'rental_term_count' => 1,
        ], $owner);

        $this->assertSame(RentalTerm::Annual, $deal->rental_term);
        $this->assertSame('monthly', $deal->payment_schedule);
    }

    public function test_a_daily_rental_is_paid_daily(): void
    {
        [$request, $owner] = $this->closedRequest();

        $deal = app(DealService::class)->create($request, [
            'agreed_total_value' => 2500,
            'deal_date' => now()->toDateString(),
            'rental_term' => RentalTerm::Daily->value,
        ], $owner);

        $this->assertSame('daily', $deal->payment_schedule);
    }

    public function test_commission_lines_keep_their_parties_and_method(): void
    {
        [$request, $owner] = $this->closedRequest();

        $deal = app(DealService::class)->create($request, [
            'agreed_total_value' => 40000,
            'deal_date' => now()->toDateString(),
            'rental_term' => RentalTerm::Monthly->value,
            'commissions' => [
                [
                    'payer' => 'المالك',
                    'beneficiary' => 'الشركة',
                    'method' => 'percentage',
                    'value' => 2.5,
                    'conditions' => 'عند التوقيع',
                ],
            ],
        ], $owner);

        $commission = $deal->commissions()->first();

        $this->assertSame('المالك', $commission->payer);
        $this->assertSame('الشركة', $commission->beneficiary);
        $this->assertSame('percentage', $commission->method->value);
        $this->assertSame('عند التوقيع', $commission->conditions);
    }

    public function test_a_non_positive_commission_is_rejected(): void
    {
        [$request, $owner] = $this->closedRequest();

        $this->expectException(ValidationException::class);

        app(DealService::class)->create($request, [
            'agreed_total_value' => 40000,
            'deal_date' => now()->toDateString(),
            'commissions' => [
                ['payer' => 'المالك', 'beneficiary' => 'الشركة', 'method' => 'fixed', 'value' => 0],
            ],
        ], $owner);
    }

    public function test_a_request_accepts_only_one_deal(): void
    {
        [$request, $owner] = $this->closedRequest();
        $service = app(DealService::class);

        $service->create($request, [
            'agreed_total_value' => 40000,
            'deal_date' => now()->toDateString(),
        ], $owner);

        $this->expectException(ValidationException::class);

        $service->create($request, [
            'agreed_total_value' => 50000,
            'deal_date' => now()->toDateString(),
        ], $owner);
    }

    public function test_the_deal_value_must_be_positive(): void
    {
        [$request, $owner] = $this->closedRequest();

        $this->expectException(ValidationException::class);

        app(DealService::class)->create($request, [
            'agreed_total_value' => 0,
            'deal_date' => now()->toDateString(),
        ], $owner);
    }

    public function test_aftercare_starts_once_a_successful_deal_exists(): void
    {
        [$request, $owner] = $this->closedRequest();

        app(DealService::class)->create($request, [
            'agreed_total_value' => 40000,
            'deal_date' => now()->toDateString(),
        ], $owner);

        $customer = $request->customer;

        $this->assertNotNull($customer->aftercare()->first());

        // A second call returns the existing record instead of duplicating it.
        app(AftercareService::class)->ensureFor($customer, $owner);

        $this->assertSame(1, $customer->aftercare()->count());
    }

    public function test_aftercare_cannot_be_started_without_a_deal(): void
    {
        $request = $this->makeRequest();

        $this->expectException(ValidationException::class);

        app(AftercareService::class)->ensureFor($request->customer, $request->owner);
    }

    public function test_a_resale_deal_stores_the_seller_details(): void
    {
        [$request, $owner] = $this->closedRequest(Department::Resale);

        $deal = app(DealService::class)->create($request, [
            'agreed_total_value' => 2500000,
            'deal_date' => now()->toDateString(),
            'seller_name' => 'مالك الوحدة',
            'seller_phone' => '+201000000000',
        ], $owner);

        $this->assertSame('مالك الوحدة', $deal->seller_name);
        $this->assertNull($deal->rental_term);
        $this->assertNull($deal->payment_schedule);
    }
}
