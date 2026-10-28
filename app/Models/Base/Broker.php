<?php

namespace App\Models\Base;

use App\Enums\RequestStatus;
use App\Models\Concerns\BelongsToDepartment;
use App\Models\Concerns\HasWhatsAppUrl;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Brokers are independent records inside one department and never matched
 * across departments (D-026). Requests keep a name/phone snapshot so history
 * stays readable after a hard deletion.
 *
 * @property string $name
 * @property string $phone E.164
 */
abstract class Broker extends Model
{
    use BelongsToDepartment, HasWhatsAppUrl, SoftDeletes;

    protected $guarded = ['id'];

    public function requests(): HasMany
    {
        return $this->hasMany(static::related('Request'), 'broker_id');
    }

    /**
     * Deleting a broker is blocked while it still has an open request (D-026).
     */
    public function hasOpenRequests(): bool
    {
        return $this->requests()->where('status', '!=', RequestStatus::Closed->value)->exists();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%'.trim($term).'%';

        return $query->where(function (Builder $query) use ($like) {
            $query->where('name', 'like', $like)
                ->orWhere('phone', 'like', $like);
        });
    }
}
