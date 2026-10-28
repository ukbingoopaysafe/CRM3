<?php

namespace App\Models\Base;

use App\Enums\Currency;
use App\Enums\RentalTerm;
use App\Models\Concerns\BelongsToDepartment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A deal is created explicitly after a successfully closed request, never
 * automatically, and at most once per request (D-036, D-047, D-058).
 *
 * @property Carbon $deal_date
 * @property Currency $currency
 * @property RentalTerm|null $rental_term
 */
abstract class Deal extends Model
{
    use BelongsToDepartment;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'deal_date' => 'date',
            'currency' => Currency::class,
            'rental_term' => RentalTerm::class,
            'agreed_total_value' => 'decimal:2',
            'unit_area' => 'decimal:2',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(static::related('Request'), 'request_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(static::related('Customer'), 'customer_id');
    }

    public function broker(): BelongsTo
    {
        return $this->belongsTo(static::related('Broker'), 'broker_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(static::related('DealCommission'), 'deal_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
