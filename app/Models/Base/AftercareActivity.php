<?php

namespace App\Models\Base;

use App\Enums\ActivityChannel;
use App\Models\Concerns\BelongsToDepartment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable aftercare interaction. Recording it updates the aftercare
 * follow-up date; leaving the date empty clears it (D-059).
 */
abstract class AftercareActivity extends Model
{
    use BelongsToDepartment;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'channel' => ActivityChannel::class,
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function aftercare(): BelongsTo
    {
        return $this->belongsTo(static::related('Aftercare'), 'aftercare_id');
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(static::related('Deal'), 'deal_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
