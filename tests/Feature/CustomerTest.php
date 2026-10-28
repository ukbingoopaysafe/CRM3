<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Enums\Outcome;
use App\Services\CustomerService;
use App\Services\RequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    public function test_phone_is_stored_as_e164_and_duplicate_is_blocked_inside_department(): void
    {
        $service = app(CustomerService::class);
        $employee = $this->employee(Department::Rental);
        $model = $this->customerClass(Department::Rental);

        $first = $service->create($model, ['name' => 'أحمد', 'phone' => '01012345678'], $employee);

        $this->assertSame('+201012345678', $first->phone);

        $this->expectException(ValidationException::class);
        $service->create($model, ['name' => 'أحمد آخر', 'phone' => '+20 101 234 5678'], $employee);
    }

    public function test_same_phone_is_allowed_in_the_other_department(): void
    {
        $service = app(CustomerService::class);
        $employee = $this->employee(Department::Rental, Department::Resale);

        $service->create($this->customerClass(Department::Rental), ['name' => 'أحمد', 'phone' => '01012345678'], $employee);
        $resale = $service->create($this->customerClass(Department::Resale), ['name' => 'أحمد', 'phone' => '01012345678'], $employee);

        $this->assertSame('+201012345678', $resale->phone);
        $this->assertSame(1, $this->customerClass(Department::Rental)::count());
        $this->assertSame(1, $this->customerClass(Department::Resale)::count());
    }

    public function test_archived_customer_keeps_phone_reserved_and_cannot_be_archived_with_open_request(): void
    {
        $service = app(CustomerService::class);
        $employee = $this->employee(Department::Rental);
        $manager = $this->manager();
        $model = $this->customerClass(Department::Rental);

        $customer = $service->create($model, ['name' => 'سامي', 'phone' => '01000000001'], $employee);
        $this->makeRequest(Department::Rental, ['_customer' => $customer, '_owner' => $employee]);

        try {
            $service->archive($customer, $manager);
            $this->fail('Archiving a customer with an open request must fail.');
        } catch (ValidationException) {
            // expected
        }

        $request = $customer->requests()->first();
        app(RequestService::class)->close($request, Outcome::Unsuccessful, $employee, 'لا يرغب حاليًا');

        $service->archive($customer->fresh(), $manager);

        $this->expectException(ValidationException::class);
        $service->create($model, ['name' => 'سامي', 'phone' => '01000000001'], $employee);
    }

    public function test_similar_name_is_only_a_warning(): void
    {
        $service = app(CustomerService::class);
        $employee = $this->employee(Department::Rental);
        $model = $this->customerClass(Department::Rental);

        $service->create($model, ['name' => 'محمد أحمد', 'phone' => '01000000002'], $employee);

        $warnings = $service->similarNames($model, 'محمد احمد');

        $this->assertCount(1, $warnings);

        // A warning never blocks the creation of a different phone.
        $second = $service->create($model, ['name' => 'محمد احمد', 'phone' => '01000000003'], $employee);
        $this->assertTrue($second->exists);
    }

    public function test_customer_records_are_isolated_per_department(): void
    {
        $service = app(CustomerService::class);
        $employee = $this->employee(Department::Rental, Department::Resale);

        $rental = $service->create($this->customerClass(Department::Rental), ['name' => 'عميل', 'phone' => '01000000004'], $employee);
        $resale = $service->create($this->customerClass(Department::Resale), ['name' => 'عميل', 'phone' => '01000000005'], $employee);

        $this->assertSame(Department::Rental, $rental::department());
        $this->assertSame(Department::Resale, $resale::department());
        $this->assertNull($rental->requests()->where('customer_id', $resale->getKey())->first());
    }
}
