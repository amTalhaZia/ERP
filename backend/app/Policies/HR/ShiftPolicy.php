<?php

namespace App\Policies\HR;

use App\Models\HR\Shift;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class ShiftPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['shifts.manage', 'attendance.view', 'employees.view']);
    }

    public function view(User $user, Shift $shift): bool
    {
        return $this->sameTenant($shift) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'shifts.manage');
    }

    public function update(User $user, Shift $shift): bool
    {
        return $this->canOn($user, 'shifts.manage', $shift);
    }

    public function delete(User $user, Shift $shift): bool
    {
        return $this->canOn($user, 'shifts.manage', $shift);
    }

    public function assign(User $user): bool
    {
        return $this->can($user, 'shifts.manage');
    }
}
