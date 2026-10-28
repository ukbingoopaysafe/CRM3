<?php

namespace App\Models\Base;

use App\Enums\Currency;
use App\Enums\RecommendationStatus;
use App\Models\Concerns\BelongsToDepartment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A contextual snapshot of a proposed unit. It is not a property catalogue
 * entry and never stores owner data or photos (D-060, DM-005/DM-006).
 *
 * @property RecommendationStatus $status
 * @property Currency|null $currency
 */
abstract class Recommendation extends Model
{
    use BelongsToDepartment;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => RecommendationStatus::class,
            'currency' => Currency::class,
            'area' => 'decimal:2',
            'price' => 'decimal:2',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(static::related('Request'), 'request_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fieldInspections(): HasMany
    {
        return $this->hasMany(static::related('FieldInspection'), 'recommendation_id')->latest('inspected_at');
    }

    public function viewings(): HasMany
    {
        return $this->hasMany(static::related('Viewing'), 'recommendation_id');
    }

    public function latestInspection(): ?FieldInspection
    {
        return $this->fieldInspections()->first();
    }

    /**
     * Inspection is only allowed after the unit was sent to or selected by
     * the customer (D-060).
     */
    public function canBeInspected(): bool
    {
        return $this->status->allowsInspection();
    }
}
