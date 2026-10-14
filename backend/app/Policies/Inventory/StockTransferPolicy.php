<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\StockTransfer;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class StockTransferPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['inventory.view', 'inventory.transfer']);
    }

    public function view(User $user, StockTransfer $transfer): bool
    {
        return $this->sameTenant($transfer) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'inventory.transfer');
    }

    public function update(User $user, StockTransfer $transfer): bool
    {
        return $this->canOn($user, 'inventory.transfer', $transfer);
    }

    public function delete(User $user, StockTransfer $transfer): bool
    {
        return $this->canOn($user, 'inventory.transfer', $transfer);
    }

    public function ship(User $user, StockTransfer $transfer): bool
    {
        return $this->canOn($user, 'inventory.transfer', $transfer);
    }

    public function receive(User $user, StockTransfer $transfer): bool
    {
        return $this->canOn($user, 'inventory.transfer', $transfer);
    }

    public function cancel(User $user, StockTransfer $transfer): bool
    {
        return $this->canOn($user, 'inventory.transfer', $transfer);
    }
}
