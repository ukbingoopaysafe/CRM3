<?php

namespace App\Services;

use App\Enums\ActivityChannel;
use App\Models\Base\Activity;
use App\Models\Base\Request;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records completed communication. The contact party and its name are frozen
 * at the moment of the event so the history stays readable (D-055).
 */
class ActivityService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function record(Request $request, array $data, User $actor): Activity
    {
        return DB::transaction(function () use ($request, $data, $actor) {
            if (! $request->acceptsActivities()) {
                throw ValidationException::withMessages([
                    'request_id' => 'لا يمكن تسجيل تواصل على طلب مغلق. استخدم متابعة ما بعد الصفقة.',
                ]);
            }

            $occurredAt = $data['occurred_at'] ?? now();

            if ($occurredAt instanceof CarbonInterface && $occurredAt->isFuture()) {
                throw ValidationException::withMessages([
                    'occurred_at' => 'وقت التواصل لا يمكن أن يكون في المستقبل.',
                ]);
            }

            $channel = $data['channel'] ?? null;
            $channel = $channel instanceof ActivityChannel ? $channel : ActivityChannel::tryFrom((string) $channel);

            if ($channel === null) {
                throw ValidationException::withMessages([
                    'channel' => 'وسيلة التواصل مطلوبة.',
                ]);
            }

            $result = trim((string) ($data['result'] ?? ''));

            if ($result === '') {
                throw ValidationException::withMessages([
                    'result' => 'نتيجة التواصل مطلوبة.',
                ]);
            }

            $contactName = $request->contactName();

            if (blank($contactName)) {
                throw ValidationException::withMessages([
                    'contact_party' => 'لا توجد جهة اتصال صالحة على الطلب لتسجيل النشاط.',
                ]);
            }

            /** @var Activity $activity */
            $activity = $request->activities()->create([
                'actor_user_id' => $actor->getKey(),
                'contact_party' => $request->contact_party->value,
                'contact_name_snapshot' => $contactName,
                'channel' => $channel->value,
                'occurred_at' => $occurredAt,
                'result' => $result,
                'notes' => $data['notes'] ?? null,
                'next_action' => $data['next_action'] ?? null,
            ]);

            // Next action text never schedules anything; the follow-up date is
            // changed explicitly by the employee (D-008, D-055).
            $this->audit->record('activity_logged', $request, $request::department(), [
                'activity_id' => $activity->getKey(),
                'channel' => $channel->value,
                'occurred_at' => $activity->occurred_at->toIso8601String(),
            ], actor: $actor);

            return $activity;
        });
    }
}
