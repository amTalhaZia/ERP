<?php

namespace App\Policies\Inventory;

use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

/** Movement history plus the ad-hoc receive / issue operations. */
class StockMovementPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'inventory.view');
    }

    public function receive(User $user): bool
    {
        return $this->can($user, 'inventory.receive');
    }

    public function issue(User $user): bool
    {
        return $this->can($user, 'inventory.issue');
    }
}
