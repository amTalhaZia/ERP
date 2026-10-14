<?php

namespace App\Policies\HR;

use App\Models\HR\AttendanceRecord;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;
use App\Policies\HR\Concerns\ResolvesOwnEmployee;

class AttendanceRecordPolicy
{
    use ChecksPermissions, ResolvesOwnEmployee;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'attendance.view');
    }

    public function view(User $user, AttendanceRecord $record): bool
    {
        return $this->canOn($user, 'attendance.view', $record)
            || ($this->sameTenant($record) && $this->ownsEmployee($user, $record->employee_id));
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'attendance.manage');
    }

    public function update(User $user, AttendanceRecord $record): bool
    {
        return $this->canOn($user, 'attendance.manage', $record);
    }

    public function delete(User $user, AttendanceRecord $record): bool
    {
        return $this->canOn($user, 'attendance.manage', $record);
    }
}
