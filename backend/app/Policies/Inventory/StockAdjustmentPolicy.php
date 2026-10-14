<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\StockAdjustment;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class StockAdjustmentPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['inventory.view', 'inventory.adjust']);
    }

    public function view(User $user, StockAdjustment $adjustment): bool
    {
        return $this->sameTenant($adjustment) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'inventory.adjust');
    }

    public function update(User $user, StockAdjustment $adjustment): bool
    {
        return $this->canOn($user, 'inventory.adjust', $adjustment);
    }

    public function delete(User $user, StockAdjustment $adjustment): bool
    {
        return $this->canOn($user, 'inventory.adjust', $adjustment);
    }

    public function post(User $user, StockAdjustment $adjustment): bool
    {
        return $this->canOn($user, 'inventory.adjust', $adjustment);
    }
}
