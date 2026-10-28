<?php

namespace App\Models\Base;

use App\Enums\ActivityChannel;
use App\Enums\ContactParty;
use App\Models\Concerns\BelongsToDepartment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A completed communication, kept as immutable history. It records who was
 * contacted and under which name at the time of the event (D-055).
 *
 * @property ActivityChannel $channel
 * @property ContactParty $contact_party
 * @property Carbon $occurred_at
 */
abstract class Activity extends Model
{
    use BelongsToDepartment;

    /** History rows carry only a creation timestamp. */
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'channel' => ActivityChannel::class,
            'contact_party' => ContactParty::class,
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(static::related('Request'), 'request_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
