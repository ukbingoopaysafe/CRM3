<?php

namespace App\Policies;

use App\Models\Base\Reassignment;
use App\Models\User;
use App\Policies\Concerns\ScopesToDepartment;

/**
 * An employee asks, the company manager decides, and only the requester may
 * withdraw while the request is pending (D-027, D-070).
 */
class ReassignmentPolicy
{
    use ScopesToDepartment;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Reassignment $reassignment): bool
    {
        return $this->inDepartment($user, $reassignment);
    }

    public function decide(User $user, Reassignment $reassignment): bool
    {
        return $user->isManager() && $this->inDepartment($user, $reassignment);
    }

    public function cancel(User $user, Reassignment $reassignment): bool
    {
        return $user->getKey() === $reassignment->requested_by
            && $this->inDepartment($user, $reassignment);
    }
}
