<?php

namespace App\Services;

use App\Enums\CommissionMethod;
use App\Enums\Currency;
use App\Enums\Outcome;
use App\Enums\RentalTerm;
use App\Models\Base\Deal;
use App\Models\Base\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A deal is an explicit, separate action after a successful close. It is
 * never created automatically and never more than once per request
 * (D-036, D-047, D-058).
 */
class DealService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AftercareService $aftercare,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Request $request, array $data, User $actor): Deal
    {
        return DB::transaction(function () use ($request, $data, $actor) {
            if (! $request->isClosed() || $request->outcome !== Outcome::Successful) {
                throw ValidationException::withMessages([
                    'request_id' => 'لا تُنشأ الصفقة إلا من طلب مغلق نتيجته ناجحة.',
                ]);
            }

            if ($request->deal()->exists()) {
                throw ValidationException::withMessages([
                    'request_id' => 'يوجد صفقة مسجلة لهذا الطلب بالفعل.',
                ]);
            }

            $value = $data['agreed_total_value'] ?? null;

            if (! is_numeric($value) || (float) $value <= 0) {
                throw ValidationException::withMessages([
                    'agreed_total_value' => 'قيمة الصفقة الإجمالية يجب أن تكون أكبر من صفر.',
                ]);
            }

            if (blank($data['deal_date'] ?? null)) {
                throw ValidationException::withMessages([
                    'deal_date' => 'تاريخ الصفقة مطلوب.',
                ]);
            }

            $currency = $data['currency'] ?? null;
            $currency = $currency instanceof Currency ? $currency : Currency::tryFrom((string) ($currency ?: Currency::EGP->value));

            if ($currency === null) {
                throw ValidationException::withMessages([
                    'currency' => 'عملة الصفقة غير صحيحة.',
                ]);
            }

            $attributes = [
                'customer_id' => $request->customer_id,
                'broker_id' => $request->broker_id,
                'deal_date' => $data['deal_date'],
                'agreed_total_value' => $value,
                'currency' => $currency->value,
                'unit_code' => $data['unit_code'] ?? null,
                'unit_type' => $data['unit_type'] ?? null,
                'unit_area' => $data['unit_area'] ?? null,
                'unit_location' => $data['unit_location'] ?? null,
                'contact_representative' => $data['contact_representative'] ?? null,
                'field_agent_name' => $data['field_agent_name'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->getKey(),
            ];

            if ($request::department()->value === 'rental') {
                $term = $data['rental_term'] ?? null;
                $term = $term instanceof RentalTerm ? $term : RentalTerm::tryFrom((string) $term);

                $attributes['rental_term'] = $term?->value;
                $attributes['rental_term_count'] = $data['rental_term_count'] ?? null;
                // Daily rentals are paid daily; every other term is monthly
                // over the full contract (D-058).
                $attributes['payment_schedule'] = $term?->paymentSchedule();
            } else {
                $attributes['seller_name'] = $data['seller_name'] ?? null;
                $attributes['seller_phone'] = $data['seller_phone'] ?? null;
            }

            /** @var Deal $deal */
            $deal = $request->deal()->create($attributes);

            $this->storeCommissions($deal, $data['commissions'] ?? []);

            $this->audit->record('deal_created', $deal, $request::department(), [
                'request_id' => $request->getKey(),
                'agreed_total_value' => (string) $deal->agreed_total_value,
                'currency' => $currency->value,
            ], actor: $actor);

            if ($request->customer_id !== null && $request->customer !== null) {
                $this->aftercare->ensureFor($request->customer, $actor);
            }

            return $deal;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $commissions
     */
    private function storeCommissions(Deal $deal, array $commissions): void
    {
        foreach ($commissions as $index => $line) {
            $value = $line['value'] ?? null;

            if (! is_numeric($value) || (float) $value <= 0) {
                throw ValidationException::withMessages([
                    "commissions.{$index}.value" => 'قيمة العمولة يجب أن تكون أكبر من صفر.',
                ]);
            }

            $method = $line['method'] ?? null;
            $method = $method instanceof CommissionMethod ? $method : CommissionMethod::tryFrom((string) $method);

            if ($method === null) {
                throw ValidationException::withMessages([
                    "commissions.{$index}.method" => 'طريقة العمولة يجب أن تكون قيمة ثابتة أو نسبة.',
                ]);
            }

            if (blank($line['payer'] ?? null) || blank($line['beneficiary'] ?? null)) {
                throw ValidationException::withMessages([
                    "commissions.{$index}.payer" => 'الدافع والمستفيد مطلوبان في بند العمولة.',
                ]);
            }

            $deal->commissions()->create([
                'payer' => $line['payer'],
                'beneficiary' => $line['beneficiary'],
                'method' => $method->value,
                'value' => $value,
                'conditions' => $line['conditions'] ?? null,
            ]);
        }
    }
}
