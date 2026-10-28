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
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A customer record belongs to exactly one department. Rental and resale
 * customers are separate tables with no shared identity (D-020).
 *
 * @property string $name
 * @property string $phone E.164
 * @property string|null $nationality
 * @property Carbon|null $archived_at
 */
abstract class Customer extends Model
{
    use BelongsToDepartment, HasWhatsAppUrl;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    public function requests(): HasMany
    {
        return $this->hasMany(static::related('Request'), 'customer_id');
    }

    public function aftercare(): HasOne
    {
        return $this->hasOne(static::related('Aftercare'), 'customer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function archiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * A customer cannot be archived while an active or paused request exists
     * (D-067).
     */
    public function hasOpenRequests(): bool
    {
        return $this->requests()->where('status', '!=', RequestStatus::Closed->value)->exists();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
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
