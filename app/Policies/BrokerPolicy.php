<?php

namespace App\Policies;

use App\Models\Base\Broker;
use App\Models\User;
use App\Policies\Concerns\ScopesToDepartment;

/**
 * Any authorised employee of the department may maintain the broker record;
 * only the company manager may delete it permanently (D-026, D-072).
 */
class BrokerPolicy
{
    use ScopesToDepartment;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Broker $broker): bool
    {
        return $this->inDepartment($user, $broker);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Broker $broker): bool
    {
        return $this->inDepartment($user, $broker);
    }

    public function delete(User $user, Broker $broker): bool
    {
        return $user->isManager() && $this->inDepartment($user, $broker);
    }
}
