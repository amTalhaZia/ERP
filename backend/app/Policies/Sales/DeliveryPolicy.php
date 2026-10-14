<?php

namespace App\Policies\Sales;

use App\Models\Sales\Delivery;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class DeliveryPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'sales_orders.view');
    }

    public function view(User $user, Delivery $model): bool
    {
        return $this->canOn($user, 'sales_orders.view', $model);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'sales_orders.manage');
    }

    public function update(User $user, Delivery $model): bool
    {
        return $this->canOn($user, 'sales_orders.manage', $model);
    }

    public function delete(User $user, Delivery $model): bool
    {
        return $this->canOn($user, 'sales_orders.manage', $model);
    }
}
