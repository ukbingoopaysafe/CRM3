<?php

namespace App\Models\Base;

use App\Enums\CommissionMethod;
use App\Models\Concerns\BelongsToDepartment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One commission agreement line. There is no default rate and no payment
 * status; this only documents what was agreed (D-037, D-058).
 */
abstract class DealCommission extends Model
{
    use BelongsToDepartment;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'method' => CommissionMethod::class,
            'value' => 'decimal:2',
        ];
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(static::related('Deal'), 'deal_id');
    }
}
