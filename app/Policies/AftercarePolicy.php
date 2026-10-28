<?php

namespace App\Policies;

use App\Models\Base\Aftercare;
use App\Models\User;
use App\Policies\Concerns\ScopesToDepartment;

/**
 * The relationship record belongs to one department and has no states or
 * closure (D-059). Interactions are written by the department's employees and
 * by whoever owns the record; the manager reads (PERMISSIONS.md §3).
 */
class AftercarePolicy
{
    use ScopesToDepartment;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Aftercare $aftercare): bool
    {
        return $this->inDepartment($user, $aftercare);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function recordActivity(User $user, Aftercare $aftercare): bool
    {
        if (! $this->inDepartment($user, $aftercare)) {
            return false;
        }

        return $user->isEmployee() || $user->getKey() === $aftercare->owner_user_id;
    }

    public function update(User $user, Aftercare $aftercare): bool
    {
        return false;
    }

    public function delete(User $user, Aftercare $aftercare): bool
    {
        return false;
    }
}
