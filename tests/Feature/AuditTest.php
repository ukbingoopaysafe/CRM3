<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Enums\Governorate;
use App\Enums\Outcome;
use App\Models\AuditEvent;
use App\Services\AreaService;
use App\Services\CustomerService;
use App\Services\RequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class AuditTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    public function test_events_carry_the_department_and_the_actor(): void
    {
        $employee = $this->employee(Department::Rental);

        app(CustomerService::class)->create(
            $this->customerClass(Department::Rental),
            ['name' => 'عميل', 'phone' => '01000000010'],
            $employee,
        );

        $event = AuditEvent::query()->latest('id')->first();

        $this->assertSame('created', $event->event_type);
        $this->assertSame(Department::Rental, $event->department);
        $this->assertSame($employee->getKey(), $event->actor_user_id);
    }

    public function test_an_employee_only_reads_their_own_events_while_the_manager_reads_the_department(): void
    {
        $first = $this->employee(Department::Rental);
        $second = $this->employee(Department::Rental);
        $service = app(CustomerService::class);

        $service->create($this->customerClass(Department::Rental), ['name' => 'أ', 'phone' => '01000000011'], $first);
        $service->create($this->customerClass(Department::Rental), ['name' => 'ب', 'phone' => '01000000012'], $second);

        $departmentEvents = AuditEvent::query()->forDepartment(Department::Rental);

        $this->assertSame(2, (clone $departmentEvents)->count());
        $this->assertSame(1, (clone $departmentEvents)->visibleTo($first)->count());
        $this->assertSame(2, (clone $departmentEvents)->visibleTo($this->manager())->count());
    }

    public function test_another_department_sees_none_of_the_events(): void
    {
        $employee = $this->employee(Department::Rental);

        app(CustomerService::class)->create(
            $this->customerClass(Department::Rental),
            ['name' => 'عميل', 'phone' => '01000000013'],
            $employee,
        );

        $this->assertSame(0, AuditEvent::query()->forDepartment(Department::Resale)->count());
    }

    public function test_free_text_reasons_are_kept_on_the_record_and_never_copied_into_the_audit(): void
    {
        $request = $this->makeRequest();
        $reason = 'العميل فضّل التأجيل لظروف عائلية';

        app(RequestService::class)->close($request, Outcome::Unsuccessful, $request->owner, $reason);

        $this->assertSame($reason, $request->fresh()->outcome_reason_text);

        $payloads = AuditEvent::query()->get()
            ->map(fn (AuditEvent $event) => json_encode($event->payload, JSON_UNESCAPED_UNICODE));

        $this->assertFalse($payloads->contains(fn (string $payload) => str_contains($payload, $reason)));
    }

    public function test_archiving_and_restoring_a_customer_is_manager_only(): void
    {
        $employee = $this->employee(Department::Rental);
        $manager = $this->manager();
        $service = app(CustomerService::class);

        $customer = $service->create(
            $this->customerClass(Department::Rental),
            ['name' => 'عميل', 'phone' => '01000000014'],
            $employee,
        );

        try {
            $service->archive($customer, $employee);
            $this->fail('Archiving is reserved for the company manager.');
        } catch (ValidationException) {
            // expected
        }

        $service->archive($customer, $manager);
        $this->assertTrue($customer->fresh()->isArchived());

        $service->restore($customer->fresh(), $manager);
        $this->assertFalse($customer->fresh()->isArchived());
    }

    public function test_employees_cannot_manage_the_area_directory(): void
    {
        $employee = $this->employee(Department::Rental);

        $this->expectException(ValidationException::class);

        app(AreaService::class)->create([
            'name' => 'الشيخ زايد',
            'governorate' => Governorate::Giza,
        ], $employee);
    }
}
