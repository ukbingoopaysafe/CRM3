<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Role rules that the backend enforces on its own, never relying on the
 * interface to hide the action (PERMISSIONS.md §4.1).
 */
final class Authorization
{
    public static function requireManager(User $user, string $message = 'هذه العملية من اختصاص مدير الشركة فقط.'): void
    {
        if (! $user->isManager()) {
            throw ValidationException::withMessages(['actor' => $message]);
        }
    }

    public static function requireSelf(User $actor, ?int $userId, string $message = 'هذه العملية من اختصاص صاحب الطلب فقط.'): void
    {
        if ($userId === null || $userId !== $actor->getKey()) {
            throw ValidationException::withMessages(['actor' => $message]);
        }
    }
}
