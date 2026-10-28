<?php

namespace App\Policies;

use App\Models\Base\Request;
use App\Models\User;
use App\Policies\Concerns\ScopesToDepartment;

/**
 * Request operations stay inside the department that owns the record. Only
 * the company manager decides a reassignment or transfers an owner directly
 * (D-027, D-070).
 */
class RequestPolicy
{
    use ScopesToDepartment;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Request $request): bool
    {
        return $this->inDepartment($user, $request);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Request $request): bool
    {
        return $this->inDepartment($user, $request);
    }

    public function changeStage(User $user, Request $request): bool
    {
        return $this->inDepartment($user, $request);
    }

    public function pause(User $user, Request $request): bool
    {
        return $this->inDepartment($user, $request);
    }

    public function resume(User $user, Request $request): bool
    {
        return $this->inDepartment($user, $request);
    }

    public function close(User $user, Request $request): bool
    {
        return $this->inDepartment($user, $request);
    }

    public function recordActivity(User $user, Request $request): bool
    {
        return $this->inDepartment($user, $request);
    }

    public function requestReassignment(User $user, Request $request): bool
    {
        return $this->inDepartment($user, $request);
    }

    public function decideReassignment(User $user, Request $request): bool
    {
        return $user->isManager() && $this->inDepartment($user, $request);
    }

    public function reassignDirect(User $user, Request $request): bool
    {
        return $user->isManager() && $this->inDepartment($user, $request);
    }

    public function createDeal(User $user, Request $request): bool
    {
        return $this->inDepartment($user, $request);
    }

    public function delete(User $user, Request $request): bool
    {
        return false;
    }
}
