<?php

namespace App\Policies\Procurement;

use App\Models\Procurement\GoodsReceipt;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class GoodsReceiptPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['purchase_orders.view', 'goods_receipts.manage']);
    }

    public function view(User $user, GoodsReceipt $receipt): bool
    {
        return $this->sameTenant($receipt) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'goods_receipts.manage');
    }

    public function update(User $user, GoodsReceipt $receipt): bool
    {
        return $this->canOn($user, 'goods_receipts.manage', $receipt);
    }

    public function delete(User $user, GoodsReceipt $receipt): bool
    {
        return $this->canOn($user, 'goods_receipts.manage', $receipt);
    }

    public function confirm(User $user, GoodsReceipt $receipt): bool
    {
        return $this->canOn($user, 'goods_receipts.manage', $receipt);
    }

    public function cancel(User $user, GoodsReceipt $receipt): bool
    {
        return $this->canOn($user, 'goods_receipts.manage', $receipt);
    }
}
