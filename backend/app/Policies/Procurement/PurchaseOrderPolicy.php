<?php

namespace App\Policies\Procurement;

use App\Models\Procurement\PurchaseOrder;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class PurchaseOrderPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'purchase_orders.view');
    }

    public function view(User $user, PurchaseOrder $order): bool
    {
        return $this->canOn($user, 'purchase_orders.view', $order);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'purchase_orders.manage');
    }

    public function update(User $user, PurchaseOrder $order): bool
    {
        return $this->canOn($user, 'purchase_orders.manage', $order);
    }

    public function delete(User $user, PurchaseOrder $order): bool
    {
        return $this->canOn($user, 'purchase_orders.manage', $order);
    }

    public function submit(User $user, PurchaseOrder $order): bool
    {
        return $this->canOn($user, 'purchase_orders.manage', $order);
    }

    public function approve(User $user, PurchaseOrder $order): bool
    {
        return $this->canOn($user, 'purchase_orders.approve', $order);
    }

    public function cancel(User $user, PurchaseOrder $order): bool
    {
        return $this->canOn($user, 'purchase_orders.manage', $order);
    }

    public function close(User $user, PurchaseOrder $order): bool
    {
        return $this->canOn($user, 'purchase_orders.manage', $order);
    }

    public function send(User $user, PurchaseOrder $order): bool
    {
        return $this->canOn($user, 'purchase_orders.manage', $order);
    }

    public function receive(User $user, PurchaseOrder $order): bool
    {
        return $this->canOn($user, 'goods_receipts.manage', $order);
    }
}
