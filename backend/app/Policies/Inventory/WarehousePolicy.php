<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\Warehouse;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class WarehousePolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'warehouses.view');
    }

    public function view(User $user, Warehouse $warehouse): bool
    {
        return $this->canOn($user, 'warehouses.view', $warehouse) && $this->branchAccessible($warehouse->branch_id);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'warehouses.manage');
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $this->canOn($user, 'warehouses.manage', $warehouse) && $this->branchAccessible($warehouse->branch_id);
    }

    public function delete(User $user, Warehouse $warehouse): bool
    {
        return $this->canOn($user, 'warehouses.manage', $warehouse) && $this->branchAccessible($warehouse->branch_id) && ! $warehouse->is_default;
    }
}
