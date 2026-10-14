<?php

namespace App\Policies\Finance;

use App\Models\Finance\Bill;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

/**
 * bills.create covers drafting, editing, deleting and submitting drafts;
 * bills.approve covers posting and voiding.
 */
class BillPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'bills.view');
    }

    public function view(User $user, Bill $bill): bool
    {
        return $this->canOn($user, 'bills.view', $bill) && $this->branchAccessible($bill->branch_id);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'bills.create');
    }

    public function update(User $user, Bill $bill): bool
    {
        return $this->canOn($user, 'bills.create', $bill) && $this->branchAccessible($bill->branch_id);
    }

    public function delete(User $user, Bill $bill): bool
    {
        return $this->update($user, $bill);
    }

    public function submit(User $user, Bill $bill): bool
    {
        return $this->update($user, $bill);
    }

    public function approve(User $user, Bill $bill): bool
    {
        return $this->canOn($user, 'bills.approve', $bill) && $this->branchAccessible($bill->branch_id);
    }

    public function void(User $user, Bill $bill): bool
    {
        return $this->approve($user, $bill);
    }
}
