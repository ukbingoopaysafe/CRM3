<?php

namespace App\Policies;

use App\Models\AuditEvent;
use App\Models\User;

/**
 * The audit trail is append-only and never edited or deleted from the
 * interface. An employee reads only their own events; the manager reads the
 * department trail (D-027, D-061, D-071).
 */
class AuditEventPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AuditEvent $event): bool
    {
        if ($user->isManager()) {
            return $event->department === null || $user->belongsToDepartment($event->department);
        }

        return $event->actor_user_id === $user->getKey();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditEvent $event): bool
    {
        return false;
    }

    public function delete(User $user, AuditEvent $event): bool
    {
        return false;
    }
}
