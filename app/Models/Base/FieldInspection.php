<?php

namespace App\Models\Base;

use App\Enums\PhotosMatch;
use App\Models\Concerns\BelongsToDepartment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The office employee documents what the field agent reported. Field agents
 * have no login account (D-039, D-060).
 */
abstract class FieldInspection extends Model
{
    use BelongsToDepartment;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'photos_match' => PhotosMatch::class,
            'inspected_at' => 'datetime',
        ];
    }

    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(static::related('Recommendation'), 'recommendation_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
