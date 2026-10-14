<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\StorageLocation;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class StorageLocationPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'warehouses.view');
    }

    public function view(User $user, StorageLocation $location): bool
    {
        return $this->canOn($user, 'warehouses.view', $location);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'warehouses.manage');
    }

    public function update(User $user, StorageLocation $location): bool
    {
        return $this->canOn($user, 'warehouses.manage', $location);
    }

    public function delete(User $user, StorageLocation $location): bool
    {
        return $this->canOn($user, 'warehouses.manage', $location);
    }
}
