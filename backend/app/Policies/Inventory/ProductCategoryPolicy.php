<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\ProductCategory;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class ProductCategoryPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'products.view');
    }

    public function view(User $user, ProductCategory $category): bool
    {
        return $this->canOn($user, 'products.view', $category);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'products.manage');
    }

    public function update(User $user, ProductCategory $category): bool
    {
        return $this->canOn($user, 'products.manage', $category);
    }

    public function delete(User $user, ProductCategory $category): bool
    {
        return $this->canOn($user, 'products.manage', $category);
    }
}
