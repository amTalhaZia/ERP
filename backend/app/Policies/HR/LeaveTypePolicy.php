<?php

namespace App\Policies\HR;

use App\Models\HR\LeaveType;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class LeaveTypePolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['leave.view', 'leave.request', 'leave.approve', 'leave.manage']);
    }

    public function view(User $user, LeaveType $leaveType): bool
    {
        return $this->sameTenant($leaveType) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'leave.manage');
    }

    public function update(User $user, LeaveType $leaveType): bool
    {
        return $this->canOn($user, 'leave.manage', $leaveType);
    }

    public function delete(User $user, LeaveType $leaveType): bool
    {
        return $this->canOn($user, 'leave.manage', $leaveType);
    }
}
