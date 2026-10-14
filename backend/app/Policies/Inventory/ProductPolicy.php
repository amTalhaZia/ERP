<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\Product;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class ProductPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'products.view');
    }

    public function view(User $user, Product $product): bool
    {
        return $this->canOn($user, 'products.view', $product);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'products.manage');
    }

    public function update(User $user, Product $product): bool
    {
        return $this->canOn($user, 'products.manage', $product);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->canOn($user, 'products.manage', $product);
    }

    /** Stock levels & movement history of a product. */
    public function viewStock(User $user, Product $product): bool
    {
        return $this->sameTenant($product) && $user->hasAnyPermission(['inventory.view', 'products.view']);
    }
}
