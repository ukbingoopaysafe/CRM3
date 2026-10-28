<?php

namespace Tests\Feature;

use App\Enums\ContactParty;
use App\Services\ActivityService;
use App\Services\RequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    public function test_activity_freezes_the_contact_party_and_name(): void
    {
        $request = $this->makeRequest();
        $activity = app(ActivityService::class)->record($request, [
            'channel' => 'call',
            'result' => 'تم الاتفاق على زيارة',
        ], $request->owner);

        $this->assertSame(ContactParty::Customer, $activity->contact_party);
        $this->assertSame('عميل اختبار', $activity->contact_name_snapshot);
        $this->assertTrue($activity->occurred_at->lessThanOrEqualTo(now()));
        $this->assertSame(1, $request->activities()->count());
    }

    public function test_a_future_occurrence_is_rejected(): void
    {
        $request = $this->makeRequest();

        $this->expectException(ValidationException::class);

        app(ActivityService::class)->record($request, [
            'channel' => 'call',
            'result' => 'مكالمة قادمة',
            'occurred_at' => now()->addDay(),
        ], $request->owner);
    }

    public function test_the_next_action_text_never_moves_the_follow_up_date(): void
    {
        $request = $this->makeRequest();
        $before = $request->next_follow_up_at;

        app(ActivityService::class)->record($request, [
            'channel' => 'whatsapp',
            'result' => 'أرسلنا الخيارات',
            'next_action' => 'اتصال بعد أسبوع',
        ], $request->owner);

        $this->assertTrue($request->fresh()->next_follow_up_at->equalTo($before));
    }

    public function test_a_paused_request_still_accepts_activities(): void
    {
        $service = app(RequestService::class);
        $request = $this->makeRequest();
        $service->pause($request, $request->owner);

        $activity = app(ActivityService::class)->record($request->fresh(), [
            'channel' => 'meeting',
            'result' => 'زيارة قصيرة',
        ], $request->owner);

        $this->assertTrue($activity->exists);
    }

    public function test_channel_and_result_are_mandatory(): void
    {
        $request = $this->makeRequest();

        $this->expectException(ValidationException::class);

        app(ActivityService::class)->record($request, [
            'channel' => 'carrier_pigeon',
            'result' => 'محاولة',
        ], $request->owner);
    }

    public function test_an_empty_result_is_rejected_even_with_a_valid_channel(): void
    {
        $request = $this->makeRequest();

        $this->expectException(ValidationException::class);

        app(ActivityService::class)->record($request, [
            'channel' => 'call',
            'result' => '  ',
        ], $request->owner);
    }
}
