<?php

namespace App\Models\Base;

use App\Models\Concerns\BelongsToDepartment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One continuing relationship record per customer inside a department. It is
 * independent from the request follow-up (D-059, DM-009).
 *
 * @property Carbon|null $next_follow_up_at
 */
abstract class Aftercare extends Model
{
    use BelongsToDepartment;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'next_follow_up_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(static::related('Customer'), 'customer_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(static::related('AftercareActivity'), 'aftercare_id')->latest('occurred_at');
    }

    /**
     * The relationship has no states, so the only operational question is
     * whether its follow-up is due (D-059).
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<', now());
    }

    public function scopeDueToday(Builder $query): Builder
    {
        return $query->whereBetween('next_follow_up_at', [now(), now()->endOfDay()]);
    }

    public function scopeDueWithinSevenDays(Builder $query): Builder
    {
        return $query->whereBetween('next_follow_up_at', [
            now()->endOfDay(),
            now()->addDays(7)->endOfDay(),
        ]);
    }

    public function scopeDueLater(Builder $query): Builder
    {
        return $query->where('next_follow_up_at', '>', now()->addDays(7)->endOfDay());
    }
}
