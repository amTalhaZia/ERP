<?php

namespace App\Policies\Procurement;

use App\Models\Procurement\PurchaseReturn;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class PurchaseReturnPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['purchase_orders.view', 'goods_receipts.manage']);
    }

    public function view(User $user, PurchaseReturn $return): bool
    {
        return $this->sameTenant($return) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'goods_receipts.manage');
    }

    public function update(User $user, PurchaseReturn $return): bool
    {
        return $this->canOn($user, 'goods_receipts.manage', $return);
    }

    public function delete(User $user, PurchaseReturn $return): bool
    {
        return $this->canOn($user, 'goods_receipts.manage', $return);
    }

    public function confirm(User $user, PurchaseReturn $return): bool
    {
        return $this->canOn($user, 'goods_receipts.manage', $return);
    }

    public function cancel(User $user, PurchaseReturn $return): bool
    {
        return $this->canOn($user, 'goods_receipts.manage', $return);
    }
}
