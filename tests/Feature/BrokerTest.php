<?php

namespace Tests\Feature;

use App\Enums\ContactParty;
use App\Enums\Department;
use App\Enums\Outcome;
use App\Services\BrokerService;
use App\Services\RequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class BrokerTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    public function test_broker_only_request_uses_broker_contact_party_and_freezes_the_snapshot(): void
    {
        $employee = $this->employee(Department::Rental);
        $broker = app(BrokerService::class)->create(
            $this->brokerClass(Department::Rental),
            ['name' => 'وسيط المعادي', 'phone' => '01098765432'],
            $employee,
        );

        $request = $this->makeRequest(Department::Rental, [
            'customer_id' => null,
            'broker_id' => $broker->getKey(),
            '_owner' => $employee,
        ]);

        $this->assertSame(ContactParty::Broker, $request->contact_party);
        $this->assertSame('وسيط المعادي', $request->broker_name_snapshot);
        $this->assertSame('+201098765432', $request->broker_phone_snapshot);
        $this->assertSame('وسيط المعادي', $request->contactName());
    }

    public function test_same_phone_is_blocked_inside_the_department_and_allowed_in_the_other(): void
    {
        $employee = $this->employee(Department::Rental, Department::Resale);
        $service = app(BrokerService::class);

        $service->create($this->brokerClass(Department::Rental), ['name' => 'وسيط', 'phone' => '01098765432'], $employee);
        $resale = $service->create($this->brokerClass(Department::Resale), ['name' => 'وسيط', 'phone' => '01098765432'], $employee);

        $this->assertSame('+201098765432', $resale->phone);

        $this->expectException(ValidationException::class);
        $service->create($this->brokerClass(Department::Rental), ['name' => 'وسيط آخر', 'phone' => '+20 109 876 5432'], $employee);
    }

    public function test_deleting_a_broker_requires_a_reason(): void
    {
        $manager = $this->manager();
        $broker = app(BrokerService::class)->create(
            $this->brokerClass(Department::Rental),
            ['name' => 'وسيط', 'phone' => '01098765432'],
            $manager,
        );

        $this->expectException(ValidationException::class);

        app(BrokerService::class)->delete($broker, '   ', $manager);
    }

    public function test_deleting_a_broker_is_blocked_while_a_request_is_still_open(): void
    {
        $manager = $this->manager();
        $employee = $this->employee(Department::Rental);
        $broker = app(BrokerService::class)->create(
            $this->brokerClass(Department::Rental),
            ['name' => 'وسيط', 'phone' => '01098765432'],
            $employee,
        );

        $this->makeRequest(Department::Rental, [
            'customer_id' => null,
            'broker_id' => $broker->getKey(),
            '_owner' => $employee,
        ]);

        $this->expectException(ValidationException::class);

        app(BrokerService::class)->delete($broker, 'وسيط مكرر', $manager);
    }

    public function test_deleting_a_broker_after_closing_keeps_the_history_readable(): void
    {
        $manager = $this->manager();
        $employee = $this->employee(Department::Rental);
        $broker = app(BrokerService::class)->create(
            $this->brokerClass(Department::Rental),
            ['name' => 'وسيط', 'phone' => '01098765432'],
            $employee,
        );

        $request = $this->makeRequest(Department::Rental, [
            'customer_id' => null,
            'broker_id' => $broker->getKey(),
            '_owner' => $employee,
        ]);

        app(RequestService::class)->close($request, Outcome::Unsuccessful, $employee, 'لم يكتمل الاتفاق');
        app(BrokerService::class)->delete($broker, 'سجل مكرر', $manager);

        $request->refresh();

        $this->assertNull($request->broker_id);
        $this->assertSame('وسيط', $request->broker_name_snapshot);
        $this->assertSame($this->brokerClass(Department::Rental), $broker::class);
        $this->assertNull($this->brokerClass(Department::Rental)::withTrashed()->find($broker->getKey()));
    }

    public function test_an_employee_cannot_delete_a_broker_record(): void
    {
        $employee = $this->employee(Department::Rental);
        $broker = app(BrokerService::class)->create(
            $this->brokerClass(Department::Rental),
            ['name' => 'وسيط', 'phone' => '01098765432'],
            $employee,
        );

        $this->expectException(ValidationException::class);

        app(BrokerService::class)->delete($broker, 'تنظيف', $employee);
    }
}
