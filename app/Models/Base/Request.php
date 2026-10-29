<?php

namespace App\Models\Base;

use App\Enums\ContactParty;
use App\Enums\Currency;
use App\Enums\Outcome;
use App\Enums\RentalTerm;
use App\Enums\RequestStatus;
use App\Enums\Stage;
use App\Models\Area;
use App\Models\Concerns\BelongsToDepartment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A request is the operational unit of a department. Stage, status and
 * outcome are three separate concepts and never substitutes (D-043).
 *
 * @property int $id
 * @property Stage $stage
 * @property RequestStatus $status
 * @property Outcome|null $outcome
 * @property ContactParty $contact_party
 * @property Carbon|null $next_follow_up_at
 * @property Carbon|null $closed_at
 */
abstract class Request extends Model
{
    use BelongsToDepartment;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'stage' => Stage::class,
            'status' => RequestStatus::class,
            'outcome' => Outcome::class,
            'contact_party' => ContactParty::class,
            'currency' => Currency::class,
            'rental_term' => RentalTerm::class,
            'next_follow_up_at' => 'datetime',
            'paused_at' => 'datetime',
            'closed_at' => 'datetime',
            'budget_declared' => 'decimal:2',
            'budget_target' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(static::related('Customer'), 'customer_id');
    }

    public function broker(): BelongsTo
    {
        return $this->belongsTo(static::related('Broker'), 'broker_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function leadSource(): BelongsTo
    {
        return $this->belongsTo(static::related('LeadSource'), 'lead_source_id');
    }

    public function closeReason(): BelongsTo
    {
        return $this->belongsTo(static::related('CloseReason'), 'outcome_reason_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(static::related('Activity'), 'request_id');
    }

    public function stageHistories(): HasMany
    {
        return $this->hasMany(static::related('StageHistory'), 'request_id')->latest('id');
    }

    public function reassignments(): HasMany
    {
        return $this->hasMany(static::related('Reassignment'), 'request_id')->latest('id');
    }

    /**
     * The pending transfer request, when one is waiting for a decision. A row
     * action reads this per record, so the list eager loads it instead of
     * asking the database once per row (D-085).
     */
    public function pendingReassignment(): HasOne
    {
        return $this->hasOne(static::related('Reassignment'), 'request_id')
            ->where('status', 'pending');
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(static::related('Recommendation'), 'request_id');
    }

    public function viewings(): HasMany
    {
        return $this->hasMany(static::related('Viewing'), 'request_id');
    }

    public function deal(): HasOne
    {
        return $this->hasOne(static::related('Deal'), 'request_id');
    }

    public function isActive(): bool
    {
        return $this->status === RequestStatus::Active;
    }

    public function isClosed(): bool
    {
        return $this->status === RequestStatus::Closed;
    }

    /**
     * Activities are only recorded on open requests; aftercare has its own
     * separate trail (D-055, DM-009).
     */
    public function acceptsActivities(): bool
    {
        return ! $this->isClosed();
    }

    public function hasDeal(): bool
    {
        return $this->deal()->exists();
    }

    /**
     * The name of the party the employee actually talks to (D-025).
     */
    public function contactName(): ?string
    {
        return $this->contact_party === ContactParty::Broker
            ? ($this->broker_name_snapshot ?? $this->broker?->name)
            : $this->customer?->name;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', RequestStatus::Active->value);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', '!=', RequestStatus::Closed->value);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->active()->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<', now());
    }

    public function scopeDueToday(Builder $query): Builder
    {
        return $query->active()->whereBetween('next_follow_up_at', [now(), now()->endOfDay()]);
    }

    public function scopeDueWithinSevenDays(Builder $query): Builder
    {
        return $query->active()->whereBetween('next_follow_up_at', [
            now()->endOfDay(),
            now()->addDays(7)->endOfDay(),
        ]);
    }

    public function scopeDueLater(Builder $query): Builder
    {
        return $query->active()->where('next_follow_up_at', '>', now()->addDays(7)->endOfDay());
    }

    public function scopeWithoutCustomer(Builder $query): Builder
    {
        return $query->whereNull('customer_id');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%'.trim($term).'%';

        return $query->where(function (Builder $query) use ($like) {
            $query->where('id', 'like', $like)
                ->orWhere('broker_name_snapshot', 'like', $like)
                ->orWhere('broker_phone_snapshot', 'like', $like)
                ->orWhereHas('customer', fn (Builder $q) => $q->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like))
                ->orWhereHas('broker', fn (Builder $q) => $q->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like));
        });
    }
}
