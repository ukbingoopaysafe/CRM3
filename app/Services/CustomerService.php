<?php

namespace App\Services;

use App\Enums\Department;
use App\Models\Base\Customer;
use App\Models\Rental\Customer as RentalCustomer;
use App\Models\Resale\Customer as ResaleCustomer;
use App\Models\User;
use App\Support\ArabicText;
use App\Support\Authorization;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Customer creation always goes through the department's own table and the
 * phone policy in D-028/D-029: an identical normalized phone blocks the
 * record, a similar name only warns.
 */
class CustomerService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  class-string<Customer>  $model
     */
    public function create(string $model, array $data, User $actor): Customer
    {
        $department = $model::department();

        return DB::transaction(function () use ($model, $data, $department, $actor) {
            $phone = Phone::normalize($data['phone'], $data['phone_country'] ?? null);

            $this->assertPhoneAvailable($model, $phone);

            /** @var Customer $customer */
            $customer = $model::create([
                'name' => trim($data['name']),
                'phone' => $phone,
                'nationality' => $data['nationality'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->getKey(),
            ]);

            $this->audit->record('created', $customer, $department, [
                'name' => $customer->name,
                'phone' => $phone,
            ], actor: $actor);

            return $customer;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Customer $customer, array $data, User $actor): Customer
    {
        return DB::transaction(function () use ($customer, $data, $actor) {
            $before = $customer->only(['name', 'phone', 'nationality']);

            if (array_key_exists('phone', $data) && filled($data['phone'])) {
                $phone = Phone::normalize($data['phone'], $data['phone_country'] ?? null);
                $this->assertPhoneAvailable($customer::class, $phone, $customer->getKey());
                $customer->phone = $phone;
            }

            if (array_key_exists('name', $data)) {
                $customer->name = trim($data['name']);
            }

            if (array_key_exists('nationality', $data)) {
                $customer->nationality = $data['nationality'];
            }

            if (array_key_exists('notes', $data)) {
                $customer->notes = $data['notes'];
            }

            $customer->save();

            $this->audit->record('updated', $customer, $customer::department(), [
                'before' => $before,
                'after' => $customer->only(['name', 'phone', 'nationality']),
            ], actor: $actor);

            return $customer;
        });
    }

    /**
     * @throws ValidationException
     */
    public function archive(Customer $customer, User $actor): Customer
    {
        Authorization::requireManager($actor);

        if ($customer->isArchived()) {
            return $customer;
        }

        if ($customer->hasOpenRequests()) {
            throw ValidationException::withMessages([
                'archived_at' => 'لا يمكن أرشفة العميل قبل إغلاق طلباته النشطة أو المتوقفة.',
            ]);
        }

        return DB::transaction(function () use ($customer, $actor) {
            $customer->forceFill([
                'archived_at' => now(),
                'archived_by' => $actor->getKey(),
            ])->save();

            $this->audit->record('archived', $customer, $customer::department(), [
                'name' => $customer->name,
            ], actor: $actor);

            return $customer;
        });
    }

    public function restore(Customer $customer, User $actor): Customer
    {
        Authorization::requireManager($actor);

        if (! $customer->isArchived()) {
            return $customer;
        }

        return DB::transaction(function () use ($customer, $actor) {
            $customer->forceFill([
                'archived_at' => null,
                'archived_by' => null,
            ])->save();

            $this->audit->record('restored', $customer, $customer::department(), [
                'name' => $customer->name,
            ], actor: $actor);

            return $customer;
        });
    }

    /**
     * Archived numbers stay reserved, so the check covers archived rows too
     * and answers with a different message for them (D-028, D-067).
     *
     * @param  class-string<Customer>  $model
     *
     * @throws ValidationException
     */
    public function assertPhoneAvailable(string $model, string $phone, ?int $ignoreId = null): void
    {
        $existing = $model::query()
            ->where('phone', $phone)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->first();

        if ($existing === null) {
            return;
        }

        throw ValidationException::withMessages([
            'phone' => $existing->isArchived()
                ? 'الرقم محجوز لسجل عميل مؤرشف ولا يمكن استخدامه لسجل جديد.'
                : 'يوجد عميل آخر داخل القسم بنفس رقم الهاتف.',
        ]);
    }

    /**
     * Names that look like the given name. This is advisory only and never
     * blocks saving (D-028, PRD-026).
     *
     * @return array<int, array{id: int, name: string, phone: string, similarity: float}>
     */
    public function similarNames(string $model, string $name, ?int $ignoreId = null, float $threshold = 0.8): array
    {
        return $model::query()
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->get(['id', 'name', 'phone', 'archived_at'])
            ->map(fn (Customer $customer) => [
                'id' => $customer->getKey(),
                'name' => $customer->name,
                'phone' => $customer->phone,
                'similarity' => ArabicText::similarity($name, $customer->name),
            ])
            ->filter(fn (array $row) => $row['similarity'] >= $threshold)
            ->sortByDesc('similarity')
            ->values()
            ->all();
    }

    /**
     * @return class-string<Customer>
     */
    public function modelFor(Department $department): string
    {
        return $department === Department::Rental ? RentalCustomer::class : ResaleCustomer::class;
    }
}
