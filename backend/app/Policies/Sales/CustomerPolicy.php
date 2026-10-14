<?php

namespace App\Policies\Sales;

use App\Models\Sales\Customer;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class CustomerPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'customers.view');
    }

    public function view(User $user, Customer $model): bool
    {
        return $this->canOn($user, 'customers.view', $model);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'customers.manage');
    }

    public function update(User $user, Customer $model): bool
    {
        return $this->canOn($user, 'customers.manage', $model);
    }

    public function delete(User $user, Customer $model): bool
    {
        return $this->canOn($user, 'customers.manage', $model);
    }
}
