<?php

namespace App\Services;

use App\Enums\ActivityChannel;
use App\Enums\Department;
use App\Models\Base\Aftercare;
use App\Models\Base\AftercareActivity;
use App\Models\Base\Customer;
use App\Models\Rental\Deal;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aftercare is one continuing relationship record per customer inside the
 * department. Interactions are immutable and update the next follow-up date
 * (D-059).
 */
class AftercareService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Created once, on the first successful deal of a known customer.
     */
    public function ensureFor(Customer $customer, User $owner): Aftercare
    {
        $existing = $customer->aftercare()->first();

        if ($existing !== null) {
            return $existing;
        }

        $hasSuccessfulDeal = $customer->requests()
            ->where('outcome', 'successful')
            ->whereHas('deal')
            ->exists();

        if (! $hasSuccessfulDeal) {
            throw ValidationException::withMessages([
                'customer_id' => 'لا يبدأ سجل ما بعد الصفقة قبل وجود صفقة ناجحة للعميل.',
            ]);
        }

        return DB::transaction(function () use ($customer, $owner) {
            /** @var Aftercare $aftercare */
            $aftercare = $customer->aftercare()->create([
                'owner_user_id' => $owner->getKey(),
            ]);

            $this->audit->record('created', $aftercare, $customer::department(), [
                'customer_id' => $customer->getKey(),
            ], actor: $owner);

            return $aftercare;
        });
    }

    public function recordActivity(Aftercare $aftercare, array $data, User $actor): AftercareActivity
    {
        $occurredAt = $data['occurred_at'] ?? now();

        if ($occurredAt instanceof CarbonInterface && $occurredAt->isFuture()) {
            throw ValidationException::withMessages([
                'occurred_at' => 'وقت التفاعل لا يمكن أن يكون في المستقبل.',
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
                'result' => 'نتيجة التفاعل مطلوبة.',
            ]);
        }

        $dealId = $data['deal_id'] ?? null;

        if ($dealId !== null && ! $this->dealBelongsToCustomer((int) $dealId, $aftercare)) {
            throw ValidationException::withMessages([
                'deal_id' => 'الصفقة المرتبطة لا تخص هذا العميل داخل القسم.',
            ]);
        }

        return DB::transaction(function () use ($aftercare, $data, $actor, $channel, $occurredAt, $result, $dealId) {
            /** @var AftercareActivity $activity */
            $activity = $aftercare->activities()->create([
                'deal_id' => $dealId,
                'channel' => $channel->value,
                'occurred_at' => $occurredAt,
                'result' => $result,
                'notes' => $data['notes'] ?? null,
                'referral_details' => $data['referral_details'] ?? null,
                'recorded_by' => $actor->getKey(),
            ]);

            // An empty follow-up date clears the previous one (D-059).
            $aftercare->forceFill([
                'next_follow_up_at' => $data['next_follow_up_at'] ?? null,
            ])->save();

            $this->audit->record('activity_logged', $aftercare, $aftercare::department(), [
                'aftercare_activity_id' => $activity->getKey(),
                'channel' => $channel->value,
            ], actor: $actor);

            return $activity;
        });
    }

    private function dealBelongsToCustomer(int $dealId, Aftercare $aftercare): bool
    {
        $deal = $aftercare::department() === Department::Rental
            ? Deal::query()->find($dealId)
            : \App\Models\Resale\Deal::query()->find($dealId);

        if ($deal === null) {
            return false;
        }

        return $deal->customer_id === $aftercare->customer_id;
    }
}
