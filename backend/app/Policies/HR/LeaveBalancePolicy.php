<?php

namespace App\Policies\HR;

use App\Models\HR\LeaveBalance;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class LeaveBalancePolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['leave.view', 'leave.manage', 'leave.approve']);
    }

    public function adjust(User $user, LeaveBalance $balance): bool
    {
        return $this->canOn($user, 'leave.manage', $balance);
    }

    public function generate(User $user): bool
    {
        return $this->can($user, 'leave.manage');
    }
}
