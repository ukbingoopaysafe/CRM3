<?php

namespace App\Services;

use App\Enums\ContactParty;
use App\Enums\Currency;
use App\Enums\Department;
use App\Enums\Outcome;
use App\Enums\RequestStatus;
use App\Enums\Stage;
use App\Models\Area;
use App\Models\Base\Request;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns the request lifecycle approved in D-043 and D-048: stage, status and
 * outcome stay separate, an active request always has a next follow-up, and a
 * losing close always carries the employee's own reason text.
 */
class RequestService
{
    /** Fields copied as-is from validated input. */
    private const SHARED_FIELDS = [
        'customer_id',
        'broker_id',
        'contact_party',
        'owner_user_id',
        'lead_source_id',
        'lead_source_other',
        'governorate',
        'area_id',
        'currency',
        'budget_declared',
        'budget_target',
        'unit_type',
        'other_requirements',
        'notes',
        'next_follow_up_at',
    ];

    private const RENTAL_FIELDS = [
        'rooms',
        'occupants',
        'is_family',
        'finishing',
        'floor_preference',
        'elevator',
        'rental_term',
        'rental_term_count',
    ];

    private const RESALE_FIELDS = [
        'area_min',
        'area_max',
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  class-string<Request>  $model
     */
    public function create(string $model, array $data, User $actor): Request
    {
        $department = $model::department();

        return DB::transaction(function () use ($model, $data, $department, $actor) {
            $attributes = $this->prepareAttributes($data, $department, $model);

            $attributes['status'] = RequestStatus::Active;
            $attributes['stage'] = Stage::Received;
            $attributes['created_by'] = $actor->getKey();
            $attributes['owner_user_id'] ??= $actor->getKey();

            $this->applyContactParty($attributes);
            $this->assertOwnerInDepartment((int) $attributes['owner_user_id'], $department);
            $this->assertAreaMatchesGovernorate($attributes);
            $this->assertFollowUpForActive(RequestStatus::Active, $attributes['next_follow_up_at'] ?? null);
            $this->assertCustomerUsable($attributes['customer_id'] ?? null, $model);
            $this->fillBrokerSnapshot($attributes, $model);

            /** @var Request $request */
            $request = $model::create($attributes);

            $request->stageHistories()->create([
                'from_stage' => null,
                'to_stage' => Stage::Received->value,
                'from_status' => null,
                'to_status' => RequestStatus::Active->value,
                'changed_by' => $actor->getKey(),
                'note' => 'إنشاء الطلب',
            ]);

            $this->audit->record('created', $request, $department, [
                'stage' => Stage::Received->value,
                'status' => RequestStatus::Active->value,
                'area_id' => $request->area_id,
                'owner_user_id' => $request->owner_user_id,
            ], actor: $actor);

            return $request;
        });
    }

    /**
     * Ownership is never changed here; it moves through a reassignment
     * decision only (D-027, D-070).
     */
    public function update(Request $request, array $data, User $actor): Request
    {
        return DB::transaction(function () use ($request, $data, $actor) {
            $this->assertOpen($request);

            $before = $request->only(['area_id', 'governorate', 'next_follow_up_at', 'contact_party']);
            $attributes = $this->prepareAttributes($data, $request::department(), $request::class, $request);
            unset($attributes['owner_user_id']);

            $this->applyContactParty($attributes, $request);
            $this->assertAreaMatchesGovernorate([
                'area_id' => $attributes['area_id'] ?? $request->area_id,
                'governorate' => $attributes['governorate'] ?? $request->governorate->value,
            ]);

            if ($request->isActive()) {
                $this->assertFollowUpForActive(
                    $request->status,
                    $attributes['next_follow_up_at'] ?? $request->next_follow_up_at
                );
            }

            if (array_key_exists('customer_id', $attributes)) {
                $this->assertCustomerUsable($attributes['customer_id'], $request::class);
            }

            if (array_key_exists('broker_id', $attributes)) {
                $this->fillBrokerSnapshot($attributes, $request::class, replace: true);
            }

            $request->fill($attributes)->save();

            $this->audit->record('updated', $request, $request::department(), [
                'before' => $before,
                'after' => $request->only(['area_id', 'governorate', 'next_follow_up_at', 'contact_party']),
            ], actor: $actor);

            return $request;
        });
    }

    public function changeStage(Request $request, Stage $stage, User $actor, ?string $note = null): Request
    {
        return DB::transaction(function () use ($request, $stage, $actor, $note) {
            $this->assertOpen($request);

            if ($request->stage === $stage) {
                return $request;
            }

            $from = $request->stage;
            $request->stage = $stage;
            $request->save();

            $request->stageHistories()->create([
                'from_stage' => $from->value,
                'to_stage' => $stage->value,
                'from_status' => $request->status->value,
                'to_status' => $request->status->value,
                'changed_by' => $actor->getKey(),
                'note' => $note,
            ]);

            $this->audit->record('stage_changed', $request, $request::department(), [
                'from' => $from->value,
                'to' => $stage->value,
            ], actor: $actor);

            return $request;
        });
    }

    /**
     * Pausing keeps the current stage; the follow-up requirement only returns
     * on resume (D-043).
     */
    public function pause(Request $request, User $actor, ?string $note = null): Request
    {
        return DB::transaction(function () use ($request, $actor, $note) {
            if ($request->status !== RequestStatus::Active) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن إيقاف طلب غير نشط.',
                ]);
            }

            $request->forceFill([
                'status' => RequestStatus::Paused,
                'paused_at' => now(),
            ])->save();

            $request->stageHistories()->create([
                'from_stage' => $request->stage->value,
                'to_stage' => $request->stage->value,
                'from_status' => RequestStatus::Active->value,
                'to_status' => RequestStatus::Paused->value,
                'changed_by' => $actor->getKey(),
                'note' => $note,
            ]);

            $this->audit->record('status_changed', $request, $request::department(), [
                'from' => RequestStatus::Active->value,
                'to' => RequestStatus::Paused->value,
            ], actor: $actor);

            return $request;
        });
    }

    public function resume(Request $request, CarbonInterface $nextFollowUp, User $actor): Request
    {
        return DB::transaction(function () use ($request, $nextFollowUp, $actor) {
            if ($request->status !== RequestStatus::Paused) {
                throw ValidationException::withMessages([
                    'status' => 'الطلب ليس متوقفًا مؤقتًا.',
                ]);
            }

            $request->forceFill([
                'status' => RequestStatus::Active,
                'paused_at' => null,
                'next_follow_up_at' => $nextFollowUp,
            ])->save();

            $request->stageHistories()->create([
                'from_stage' => $request->stage->value,
                'to_stage' => $request->stage->value,
                'from_status' => RequestStatus::Paused->value,
                'to_status' => RequestStatus::Active->value,
                'changed_by' => $actor->getKey(),
                'note' => 'استئناف الطلب',
            ]);

            $this->audit->record('status_changed', $request, $request::department(), [
                'from' => RequestStatus::Paused->value,
                'to' => RequestStatus::Active->value,
            ], actor: $actor);

            return $request;
        });
    }

    public function setFollowUp(Request $request, CarbonInterface $at, User $actor): Request
    {
        $request->forceFill(['next_follow_up_at' => $at])->save();

        $this->audit->record('updated', $request, $request::department(), [
            'next_follow_up_at' => $at->toIso8601String(),
        ], actor: $actor);

        return $request;
    }

    /**
     * Closing never creates a deal (D-047); a losing close requires the
     * employee's free-text reason (D-050). The reason text stays on the
     * request and is not copied into the audit payload (D-061).
     */
    public function close(
        Request $request,
        Outcome $outcome,
        User $actor,
        ?string $reasonText = null,
        ?int $reasonId = null,
        ?string $notes = null,
    ): Request {
        return DB::transaction(function () use ($request, $outcome, $actor, $reasonText, $reasonId, $notes) {
            $this->assertOpen($request);

            $reasonText = $reasonText === null ? null : trim($reasonText);

            if ($outcome === Outcome::Unsuccessful && ($reasonText === null || $reasonText === '')) {
                throw ValidationException::withMessages([
                    'outcome_reason_text' => 'سبب الإغلاق غير الناجح مطلوب بصياغة الموظف.',
                ]);
            }

            if ($outcome === Outcome::Successful) {
                $reasonText = null;
                $reasonId = null;
            }

            if ($reasonId !== null) {
                $this->assertReasonInDepartment($reasonId, $request);
            }

            $from = $request->status;
            $stage = $request->stage;

            $request->forceFill([
                'status' => RequestStatus::Closed,
                'outcome' => $outcome,
                'outcome_reason_text' => $reasonText,
                'outcome_reason_id' => $reasonId,
                'outcome_notes' => $notes,
                'closed_at' => now(),
                'paused_at' => null,
            ])->save();

            $request->stageHistories()->create([
                'from_stage' => $stage->value,
                'to_stage' => $stage->value,
                'from_status' => $from->value,
                'to_status' => RequestStatus::Closed->value,
                'changed_by' => $actor->getKey(),
                'note' => $outcome === Outcome::Successful ? 'إغلاق ناجح' : 'إغلاق غير ناجح',
            ]);

            $this->audit->record('status_changed', $request, $request::department(), [
                'from' => $from->value,
                'to' => RequestStatus::Closed->value,
                'outcome' => $outcome->value,
                'outcome_reason_id' => $reasonId,
            ], actor: $actor);

            return $request;
        });
    }

    /**
     * @throws ValidationException
     */
    public function assertOpen(Request $request): void
    {
        if ($request->isClosed()) {
            throw ValidationException::withMessages([
                'status' => 'الطلب مغلق ولا يقبل تعديلًا تشغيليًا.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareAttributes(
        array $data,
        Department $department,
        string $model,
        ?Request $existing = null,
    ): array {
        $fields = array_merge(
            self::SHARED_FIELDS,
            $department === Department::Rental ? self::RENTAL_FIELDS : self::RESALE_FIELDS,
        );

        $attributes = Arr::only($data, $fields);

        $budgetFilled = filled($attributes['budget_declared'] ?? $existing?->budget_declared)
            || filled($attributes['budget_target'] ?? $existing?->budget_target);

        if (! $budgetFilled) {
            $attributes['currency'] = null;
        } elseif (blank($attributes['currency'] ?? $existing?->currency)) {
            $attributes['currency'] = Currency::EGP->value;
        }

        return $attributes;
    }

    /**
     * With one party the contact party is determined; with both the employee
     * must choose explicitly (D-049).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function applyContactParty(array &$attributes, ?Request $existing = null): void
    {
        $customerId = $attributes['customer_id'] ?? $existing?->customer_id;
        $brokerId = $attributes['broker_id'] ?? $existing?->broker_id;

        if ($customerId === null && $brokerId === null) {
            throw ValidationException::withMessages([
                'customer_id' => 'يجب ربط الطلب بعميل معروف أو بوسيط على الأقل.',
            ]);
        }

        if ($customerId === null) {
            $attributes['contact_party'] = ContactParty::Broker;

            return;
        }

        if ($brokerId === null) {
            $attributes['contact_party'] = ContactParty::Customer;

            return;
        }

        $raw = $attributes['contact_party'] ?? $existing?->contact_party;
        $party = $raw instanceof ContactParty ? $raw : ContactParty::tryFrom((string) $raw);

        if ($party === null) {
            throw ValidationException::withMessages([
                'contact_party' => 'يجب تحديد جهة الاتصال الفعلية عند وجود عميل ووسيط.',
            ]);
        }

        $attributes['contact_party'] = $party;
    }

    private function assertOwnerInDepartment(int $ownerId, Department $department): void
    {
        $owner = User::query()->with('memberships')->find($ownerId);

        if ($owner === null || ! $owner->isActive()) {
            throw ValidationException::withMessages([
                'owner_user_id' => 'يجب اختيار موظف نشط مسؤولًا عن الطلب.',
            ]);
        }

        if (! $owner->belongsToDepartment($department)) {
            throw ValidationException::withMessages([
                'owner_user_id' => 'الموظف المسؤول لا يعمل في هذا القسم.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assertAreaMatchesGovernorate(array $attributes): void
    {
        $area = Area::query()->find($attributes['area_id'] ?? null);

        if ($area === null) {
            throw ValidationException::withMessages([
                'area_id' => 'يجب اختيار منطقة من قائمة المناطق المعتمدة.',
            ]);
        }

        if ($area->isArchived()) {
            throw ValidationException::withMessages([
                'area_id' => 'المنطقة المؤرشفة لا تُستخدم في طلب جديد.',
            ]);
        }

        if ($area->governorate->value !== ($attributes['governorate'] ?? null)) {
            throw ValidationException::withMessages([
                'area_id' => 'المنطقة المختارة لا تتبع المحافظة المحددة.',
            ]);
        }
    }

    private function assertFollowUpForActive(RequestStatus $status, mixed $followUp): void
    {
        if ($status === RequestStatus::Active && blank($followUp)) {
            throw ValidationException::withMessages([
                'next_follow_up_at' => 'الطلب النشط يتطلب موعد متابعة قادمًا.',
            ]);
        }
    }

    private function assertCustomerUsable(?int $customerId, string $model): void
    {
        if ($customerId === null) {
            return;
        }

        $class = $this->departmentClass($model, 'Customer');
        $customer = $class::query()->find($customerId);

        if ($customer === null) {
            throw ValidationException::withMessages([
                'customer_id' => 'سجل العميل غير موجود داخل هذا القسم.',
            ]);
        }

        if ($customer->isArchived()) {
            throw ValidationException::withMessages([
                'customer_id' => 'لا يمكن ربط طلب جديد بعميل مؤرشف.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function fillBrokerSnapshot(array &$attributes, string $model, bool $replace = false): void
    {
        if (blank($attributes['broker_id'] ?? null)) {
            if ($replace) {
                $attributes['broker_name_snapshot'] = null;
                $attributes['broker_phone_snapshot'] = null;
            }

            return;
        }

        $class = $this->departmentClass($model, 'Broker');
        $broker = $class::query()->find($attributes['broker_id']);

        if ($broker === null) {
            throw ValidationException::withMessages([
                'broker_id' => 'سجل الوسيط غير موجود داخل هذا القسم.',
            ]);
        }

        $attributes['broker_name_snapshot'] = $broker->name;
        $attributes['broker_phone_snapshot'] = $broker->phone;
    }

    private function assertReasonInDepartment(int $reasonId, Request $request): void
    {
        $class = $this->departmentClass($request::class, 'CloseReason');

        if (! $class::query()->whereKey($reasonId)->exists()) {
            throw ValidationException::withMessages([
                'outcome_reason_id' => 'تصنيف سبب الإغلاق لا يخص هذا القسم.',
            ]);
        }
    }

    /**
     * @return class-string
     */
    private function departmentClass(string $model, string $suffix): string
    {
        return $model::department() === Department::Rental
            ? 'App\\Models\\Rental\\'.$suffix
            : 'App\\Models\\Resale\\'.$suffix;
    }
}
