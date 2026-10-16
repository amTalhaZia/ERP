<?php

namespace App\Policies\HR;

use App\Models\HR\Holiday;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class HolidayPolicy
{
    use ChecksPermissions;

    /** Holidays are not sensitive: every member of the company may see them (managing still needs shifts.manage). */
    public function viewAny(User $user): bool
    {
        return true;
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
