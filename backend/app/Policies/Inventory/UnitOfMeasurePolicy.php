<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\UnitOfMeasure;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class UnitOfMeasurePolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'products.view');
    }

    public function view(User $user, UnitOfMeasure $uom): bool
    {
        return $this->canOn($user, 'products.view', $uom);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'products.manage');
    }

    public function update(User $user, UnitOfMeasure $uom): bool
    {
        return $this->canOn($user, 'products.manage', $uom);
    }

    public function delete(User $user, UnitOfMeasure $uom): bool
    {
        return $this->canOn($user, 'products.manage', $uom);
    }
}
