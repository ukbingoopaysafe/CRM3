<?php

namespace App\Policies;

use App\Models\User;

/**
 * Staff accounts are the company manager's business alone (D-080). Nobody
 * deletes an account: deactivation keeps the historical records attached to
 * the name that made them (D-071).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isManager();
    }

    public function view(User $user, User $account): bool
    {
        return $user->isManager();
    }

    public function create(User $user): bool
    {
        return $user->isManager();
    }

    public function update(User $user, User $account): bool
    {
        return $user->isManager();
    }

    public function deactivate(User $user, User $account): bool
    {
        return $user->isManager() && ! $user->is($account);
    }

    public function delete(User $user, User $account): bool
    {
        return false;
    }
}
