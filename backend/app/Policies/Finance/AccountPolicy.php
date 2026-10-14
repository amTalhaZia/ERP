<?php

namespace App\Policies\Finance;

use App\Models\Finance\Account;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class AccountPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'accounts.view');
    }

    public function view(User $user, Account $account): bool
    {
        return $this->canOn($user, 'accounts.view', $account);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'accounts.manage');
    }

    public function update(User $user, Account $account): bool
    {
        return $this->canOn($user, 'accounts.manage', $account);
    }

    public function delete(User $user, Account $account): bool
    {
        return $this->canOn($user, 'accounts.manage', $account) && ! $account->is_system;
    }
}
