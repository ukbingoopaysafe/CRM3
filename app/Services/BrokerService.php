<?php

namespace App\Services;

use App\Models\Base\Broker;
use App\Models\User;
use App\Support\ArabicText;
use App\Support\Authorization;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Broker records live inside one department (D-026). Deleting is manager-only,
 * permanent, requires a reason, is blocked while an open request exists and
 * keeps the name/phone snapshot on the historical requests (D-072).
 */
class BrokerService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  class-string<Broker>  $model
     */
    public function create(string $model, array $data, User $actor): Broker
    {
        return DB::transaction(function () use ($model, $data, $actor) {
            $phone = Phone::normalize($data['phone'], $data['phone_country'] ?? null);

            $this->assertPhoneAvailable($model, $phone);

            /** @var Broker $broker */
            $broker = $model::create([
                'name' => trim($data['name']),
                'phone' => $phone,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->getKey(),
            ]);

            $this->audit->record('created', $broker, $broker::department(), [
                'name' => $broker->name,
                'phone' => $phone,
            ], actor: $actor);

            return $broker;
        });
    }

    public function update(Broker $broker, array $data, User $actor): Broker
    {
        return DB::transaction(function () use ($broker, $data, $actor) {
            $before = $broker->only(['name', 'phone']);

            if (array_key_exists('phone', $data) && filled($data['phone'])) {
                $phone = Phone::normalize($data['phone'], $data['phone_country'] ?? null);
                $this->assertPhoneAvailable($broker::class, $phone, $broker->getKey());
                $broker->phone = $phone;
            }

            if (array_key_exists('name', $data)) {
                $broker->name = trim($data['name']);
            }

            if (array_key_exists('notes', $data)) {
                $broker->notes = $data['notes'];
            }

            $broker->save();

            $this->audit->record('updated', $broker, $broker::department(), [
                'before' => $before,
                'after' => $broker->only(['name', 'phone']),
            ], actor: $actor);

            return $broker;
        });
    }

    /**
     * @throws ValidationException
     */
    public function delete(Broker $broker, string $reason, User $actor): void
    {
        Authorization::requireManager($actor, 'حذف سجل الوسيط من اختصاص مدير الشركة فقط.');

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'سبب حذف الوسيط مطلوب.',
            ]);
        }

        if ($broker->hasOpenRequests()) {
            throw ValidationException::withMessages([
                'broker' => 'لا يمكن حذف وسيط له طلب نشط. أغلق الطلبات أو أعد تعيينها أولًا.',
            ]);
        }

        DB::transaction(function () use ($broker, $reason, $actor) {
            $broker->requests()->each(function ($request) use ($broker) {
                $request->forceFill([
                    'broker_name_snapshot' => $request->broker_name_snapshot ?: $broker->name,
                    'broker_phone_snapshot' => $request->broker_phone_snapshot ?: $broker->phone,
                ])->save();
            });

            $this->audit->record('deleted', $broker, $broker::department(), [
                'name' => $broker->name,
                'phone' => $broker->phone,
            ], $reason, $actor);

            $broker->forceDelete();
        });
    }

    /**
     * @param  class-string<Broker>  $model
     *
     * @throws ValidationException
     */
    public function assertPhoneAvailable(string $model, string $phone, ?int $ignoreId = null): void
    {
        $exists = $model::withTrashed()
            ->where('phone', $phone)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'phone' => 'يوجد وسيط آخر داخل القسم بنفس رقم الهاتف.',
            ]);
        }
    }

    public function similarNames(string $model, string $name, ?int $ignoreId = null, float $threshold = 0.8): array
    {
        return $model::query()
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->get(['id', 'name', 'phone'])
            ->map(fn (Broker $broker) => [
                'id' => $broker->getKey(),
                'name' => $broker->name,
                'phone' => $broker->phone,
                'similarity' => ArabicText::similarity($name, $broker->name),
            ])
            ->filter(fn (array $row) => $row['similarity'] >= $threshold)
            ->sortByDesc('similarity')
            ->values()
            ->all();
    }
}
