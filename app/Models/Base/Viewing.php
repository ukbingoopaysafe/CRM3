<?php

namespace App\Models\Base;

use App\Enums\ViewingResult;
use App\Models\Concerns\BelongsToDepartment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A customer viewing appointment and its outcome. A viewing is never a deal
 * by itself (D-060).
 *
 * @property ViewingResult $result
 * @property Carbon $scheduled_at
 */
abstract class Viewing extends Model
{
    use BelongsToDepartment;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'result' => ViewingResult::class,
            'scheduled_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(static::related('Request'), 'request_id');
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
