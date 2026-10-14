<?php

namespace App\Policies\Procurement;

use App\Models\Procurement\Supplier;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class SupplierPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'suppliers.view');
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $this->canOn($user, 'suppliers.view', $supplier);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'suppliers.manage');
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $this->canOn($user, 'suppliers.manage', $supplier);
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $this->canOn($user, 'suppliers.manage', $supplier);
    }

    public function approve(User $user, Supplier $supplier): bool
    {
        return $this->canOn($user, 'suppliers.manage', $supplier);
    }
}
