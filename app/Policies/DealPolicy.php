<?php

namespace App\Policies;

use App\Models\Base\Deal;
use App\Models\User;
use App\Policies\Concerns\ScopesToDepartment;

/**
 * A deal is written once, inside the department that owns its request
 * (D-036, D-058). There is no update or delete path: the agreement is a
 * record of what happened, not a draft.
 */
class DealPolicy
{
    use ScopesToDepartment;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Deal $deal): bool
    {
        return $this->inDepartment($user, $deal);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Deal $deal): bool
    {
        return false;
    }

    public function delete(User $user, Deal $deal): bool
    {
        return false;
    }
}
