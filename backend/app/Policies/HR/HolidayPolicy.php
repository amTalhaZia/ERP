<?php

namespace App\Policies\HR;

use App\Models\HR\Holiday;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class HolidayPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['shifts.manage', 'attendance.view', 'employees.view', 'leave.request', 'leave.view']);
    }

    public function view(User $user, Holiday $holiday): bool
    {
        return $this->sameTenant($holiday) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'shifts.manage');
    }

    public function update(User $user, Holiday $holiday): bool
    {
        return $this->canOn($user, 'shifts.manage', $holiday);
    }

    public function delete(User $user, Holiday $holiday): bool
    {
        return $this->canOn($user, 'shifts.manage', $holiday);
    }
}
