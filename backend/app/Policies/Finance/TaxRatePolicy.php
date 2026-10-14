<?php

namespace App\Policies\Finance;

use App\Models\Finance\TaxRate;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class TaxRatePolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['accounts.view', 'tax.manage']);
    }

    public function view(User $user, TaxRate $taxRate): bool
    {
        return $this->sameTenant($taxRate) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'tax.manage');
    }

    public function update(User $user, TaxRate $taxRate): bool
    {
        return $this->canOn($user, 'tax.manage', $taxRate);
    }

    public function delete(User $user, TaxRate $taxRate): bool
    {
        return $this->canOn($user, 'tax.manage', $taxRate);
    }
}
