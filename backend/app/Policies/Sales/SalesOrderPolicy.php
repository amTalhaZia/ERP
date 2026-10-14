<?php

namespace App\Policies\Sales;

use App\Models\Sales\SalesOrder;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class SalesOrderPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'sales_orders.view');
    }

    public function view(User $user, SalesOrder $model): bool
    {
        return $this->canOn($user, 'sales_orders.view', $model);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'sales_orders.manage');
    }

    public function update(User $user, SalesOrder $model): bool
    {
        return $this->canOn($user, 'sales_orders.manage', $model);
    }

    public function delete(User $user, SalesOrder $model): bool
    {
        return $this->canOn($user, 'sales_orders.manage', $model);
    }

    public function approve(User $user, SalesOrder $model): bool
    {
        return $this->canOn($user, 'sales_orders.approve', $model);
    }
}
