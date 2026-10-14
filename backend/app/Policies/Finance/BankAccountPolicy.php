<?php

namespace App\Policies\Finance;

use App\Models\Finance\BankAccount;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class BankAccountPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['accounts.view', 'bank.manage']);
    }

    public function view(User $user, BankAccount $bankAccount): bool
    {
        return $this->sameTenant($bankAccount) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'bank.manage');
    }

    public function update(User $user, BankAccount $bankAccount): bool
    {
        return $this->canOn($user, 'bank.manage', $bankAccount);
    }

    public function delete(User $user, BankAccount $bankAccount): bool
    {
        return $this->canOn($user, 'bank.manage', $bankAccount);
    }
}
