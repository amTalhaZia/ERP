<?php

namespace App\Policies\Inventory;

use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

/** Stock levels and the valuation / movement-summary reports. */
class StockLevelPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'inventory.view');
    }
}
