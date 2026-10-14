<?php

namespace App\Policies\Finance;

use App\Models\Finance\FinancialPeriod;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class FinancialPeriodPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['accounts.view', 'journals.view', 'periods.manage']);
    }

    public function view(User $user, FinancialPeriod $period): bool
    {
        return $this->sameTenant($period) && $this->viewAny($user);
    }

    public function close(User $user, FinancialPeriod $period): bool
    {
        return $this->canOn($user, 'periods.manage', $period);
    }

    public function reopen(User $user, FinancialPeriod $period): bool
    {
        return $this->canOn($user, 'periods.manage', $period);
    }
}
