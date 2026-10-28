<?php

namespace App\Policies;

use App\Models\Base\Customer;
use App\Models\User;
use App\Policies\Concerns\ScopesToDepartment;

/**
 * Customers are readable and editable by the employees of their own
 * department. Archiving and restoring belong to the company manager only
 * (D-067); there is no permanent delete (D-072).
 */
class CustomerPolicy
{
    use ScopesToDepartment;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->inDepartment($user, $customer);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->inDepartment($user, $customer);
    }

    public function archive(User $user, Customer $customer): bool
    {
        return $user->isManager() && $this->inDepartment($user, $customer);
    }

    public function restore(User $user, Customer $customer): bool
    {
        return $user->isManager() && $this->inDepartment($user, $customer);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return false;
    }
}
