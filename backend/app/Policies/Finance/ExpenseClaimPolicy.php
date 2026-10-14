<?php

namespace App\Policies\Finance;

use App\Models\Finance\ExpenseClaim;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

/**
 * expenses.view / expenses.approve see every claim of the company; a user
 * with only expenses.submit sees and edits their own claims. Nobody may
 * approve or reject their own claim.
 */
class ExpenseClaimPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'expenses.view') || $this->can($user, 'expenses.approve') || $this->can($user, 'expenses.submit');
    }

    /** Whether the user may see claims other than their own. */
    public function viewAll(User $user): bool
    {
        return $this->can($user, 'expenses.view') || $this->can($user, 'expenses.approve');
    }

    public function view(User $user, ExpenseClaim $claim): bool
    {
        if (! $this->sameTenant($claim)) {
            return false;
        }

        return $this->viewAll($user) || ($claim->isOwnedBy($user) && $this->can($user, 'expenses.submit'));
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'expenses.submit');
    }

    public function update(User $user, ExpenseClaim $claim): bool
    {
        return $this->canOn($user, 'expenses.submit', $claim) && $claim->isOwnedBy($user);
    }

    public function delete(User $user, ExpenseClaim $claim): bool
    {
        return $this->update($user, $claim);
    }

    public function submit(User $user, ExpenseClaim $claim): bool
    {
        return $this->update($user, $claim);
    }

    public function approve(User $user, ExpenseClaim $claim): bool
    {
        return $this->canOn($user, 'expenses.approve', $claim) && ! $claim->isOwnedBy($user);
    }

    public function reject(User $user, ExpenseClaim $claim): bool
    {
        return $this->approve($user, $claim);
    }
}
