<?php

namespace App\Services;

use App\Enums\Department;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes the append-only audit trail. Free-text notes and results are never
 * copied into the payload; only operational values needed for review are
 * stored (D-061, D-071).
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(
        string $eventType,
        Model $subject,
        ?Department $department = null,
        ?array $payload = null,
        ?string $reason = null,
        ?User $actor = null,
    ): AuditEvent {
        $actor ??= auth()->user();

        return AuditEvent::create([
            'department' => $department?->value,
            'event_type' => $eventType,
            'auditable_type' => $subject->getMorphClass(),
            'auditable_id' => $subject->getKey(),
            'actor_user_id' => $actor?->getKey(),
            'payload' => $payload,
            'reason' => $reason,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
