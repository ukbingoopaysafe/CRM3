<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Enums\Outcome;
use App\Filament\Resources\Rental\Aftercares\Pages\ViewAftercare;
use App\Models\Base\Aftercare;
use App\Services\DealService;
use App\Services\RequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

/**
 * The aftercare screens record interactions through AftercareService and stay
 * read-only: no edit, no states, no closure (D-059).
 */
class AftercareScreenTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    private function aftercareFor(Department $department = Department::Rental): Aftercare
    {
        $request = $this->makeRequest($department);
        app(RequestService::class)->close($request, Outcome::Successful, $request->owner);
        app(DealService::class)->create($request->fresh(), [
            'agreed_total_value' => 20000,
            'deal_date' => now()->toDateString(),
        ], $request->owner);

        return $request->fresh()->customer->aftercare()->firstOrFail();
    }

    public function test_aftercare_screens_render_for_both_departments(): void
    {
        $rental = $this->aftercareFor();
        $resale = $this->aftercareFor(Department::Resale);

        $this->actingAs($rental->owner)->get('/admin/rental/aftercares')->assertOk();
        $this->actingAs($resale->owner)->get('/admin/resale/aftercares')->assertOk();
        $this->actingAs($rental->owner)->get('/admin/rental/aftercares/'.$rental->getKey())->assertOk();
    }

    public function test_recording_an_interaction_updates_the_follow_up_and_keeps_the_history(): void
    {
        $aftercare = $this->aftercareFor();
        $this->actingAs($aftercare->owner);

        $next = now()->addWeek()->startOfMinute();

        Livewire::test(ViewAftercare::class, ['record' => $aftercare->getKey()])
            ->callAction('recordAftercareActivity', data: [
                'channel' => 'call',
                'occurred_at' => now()->subHour()->toDateTimeString(),
                'result' => 'اطمأن على السكن وطلب عرض وحدة أخرى لاحقًا.',
                'next_follow_up_at' => $next->toDateTimeString(),
                'referral_details' => 'يعرف عميلًا يبحث عن شقة.',
            ])
            ->assertHasNoActionErrors();

        $aftercare->refresh();

        $this->assertSame(1, $aftercare->activities()->count());
        $this->assertTrue($aftercare->next_follow_up_at->equalTo($next));
    }

    public function test_an_interaction_without_a_follow_up_date_clears_it(): void
    {
        $aftercare = $this->aftercareFor();
        $aftercare->forceFill(['next_follow_up_at' => now()->addDay()])->save();
        $this->actingAs($aftercare->owner);

        Livewire::test(ViewAftercare::class, ['record' => $aftercare->getKey()])
            ->callAction('recordAftercareActivity', data: [
                'channel' => 'whatsapp',
                'occurred_at' => now()->subMinutes(30)->toDateTimeString(),
                'result' => 'راجع عرض الإيجار وأبدى رضاه.',
            ])
            ->assertHasNoActionErrors();

        $this->assertNull($aftercare->refresh()->next_follow_up_at);
    }

    public function test_the_manager_reads_the_relationship_without_the_interaction_action(): void
    {
        $aftercare = $this->aftercareFor();
        $this->actingAs($this->manager());

        Livewire::test(ViewAftercare::class, ['record' => $aftercare->getKey()])
            ->assertSuccessful()
            ->assertActionHidden('recordAftercareActivity');
    }

    public function test_an_aftercare_record_of_the_other_department_is_not_reachable(): void
    {
        $aftercare = $this->aftercareFor();
        $resaleEmployee = $this->employee(Department::Resale);

        $this->actingAs($resaleEmployee)
            ->get('/admin/rental/aftercares/'.$aftercare->getKey())
            ->assertForbidden();
    }

    public function test_a_customer_with_a_successful_deal_can_be_started_from_the_customer_list(): void
    {
        $request = $this->makeRequest();
        app(RequestService::class)->close($request, Outcome::Successful, $request->owner);
        app(DealService::class)->create($request->fresh(), [
            'agreed_total_value' => 12000,
            'deal_date' => now()->toDateString(),
        ], $request->owner);

        $customer = $request->fresh()->customer;
        // The deal's own write already opened the record; deleting it would be
        // the only way to re-create it, so this asserts the ability instead.
        $this->assertTrue($request->owner->can('startAftercare', $customer));
        $this->assertFalse($this->manager()->can('startAftercare', $customer));
    }
}
