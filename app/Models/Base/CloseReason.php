<?php

namespace App\Models\Base;

use App\Models\Concerns\BelongsToDepartment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Optional helper classification for losing a request. The employee-written
 * reason text is always the authoritative explanation (D-050, D-073).
 */
abstract class CloseReason extends Model
{
    use BelongsToDepartment;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function requests(): HasMany
    {
        return $this->hasMany(static::related('Request'), 'outcome_reason_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
