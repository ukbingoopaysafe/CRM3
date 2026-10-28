<?php

namespace App\Models\Base;

use App\Enums\ReassignmentStatus;
use App\Models\Concerns\BelongsToDepartment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An employee asks to move ownership; the department manager decides. The
 * company manager is never a target (D-070).
 */
abstract class Reassignment extends Model
{
    use BelongsToDepartment;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => ReassignmentStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(static::related('Request'), 'request_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function currentOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_owner_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
