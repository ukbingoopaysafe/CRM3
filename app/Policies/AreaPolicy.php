<?php

namespace App\Policies;

use App\Models\Area;
use App\Models\User;

/**
 * The area directory is a single shared reference list (D-081). The company
 * manager maintains it, and a rental employee may only add an area when the
 * manager granted that narrow permission (D-032, D-066). Nothing but a
 * manager may rename, archive or restore an area (D-067).
 */
class AreaPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Area $area): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canManageAreas();
    }

    public function update(User $user, Area $area): bool
    {
        return $user->isManager();
    }

    public function archive(User $user, Area $area): bool
    {
        return $user->isManager();
    }

    public function restore(User $user, Area $area): bool
    {
        return $user->isManager();
    }

    public function delete(User $user, Area $area): bool
    {
        return false;
    }
}
