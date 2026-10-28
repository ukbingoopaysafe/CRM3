<?php

namespace App\Models;

use App\Enums\Governorate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One company-wide area directory shared by both departments (D-081).
 * Areas are archived, never deleted, and archived rows stay visible on old
 * requests but disappear from new request choices (D-067).
 *
 * @property string $name
 * @property Governorate $governorate
 * @property Carbon|null $archived_at
 */
class Area extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'governorate' => Governorate::class,
            'is_active' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function rentalRequests(): HasMany
    {
        return $this->hasMany(Rental\Request::class);
    }

    public function resaleRequests(): HasMany
    {
        return $this->hasMany(Resale\Request::class);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function scopeSelectable(Builder $query): Builder
    {
        return $query->whereNull('archived_at')->where('is_active', true);
    }

    public function scopeForGovernorate(Builder $query, Governorate|string|null $governorate): Builder
    {
        if ($governorate === null || $governorate === '') {
            return $query;
        }

        $value = $governorate instanceof Governorate ? $governorate->value : $governorate;

        return $query->where('governorate', $value);
    }

    /**
     * Display label used in selects, e.g. "المعادي — القاهرة".
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name.' — '.$this->governorate->getLabel();
    }
}
