<?php

namespace App\Policies\Finance;

use App\Models\Finance\FinancialYear;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class FinancialYearPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['accounts.view', 'journals.view', 'periods.manage']);
    }

    public function view(User $user, FinancialYear $year): bool
    {
        return $this->sameTenant($year) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'periods.manage');
    }
}
