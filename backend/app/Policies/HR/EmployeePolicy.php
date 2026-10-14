<?php

namespace App\Policies\HR;

use App\Models\HR\Employee;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class EmployeePolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'employees.view');
    }

    /** Viewers, plus every member for their own record (self-service). */
    public function view(User $user, Employee $employee): bool
    {
        return $this->canOn($user, 'employees.view', $employee)
            || ($this->sameTenant($employee) && $employee->user_id === $user->id);
    }

    /** Masked bank/tax identifiers are only shown to editors. */
    public function viewSensitive(User $user, Employee $employee): bool
    {
        return $this->canOn($user, 'employees.update', $employee);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'employees.create');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $this->canOn($user, 'employees.update', $employee);
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $this->canOn($user, 'employees.delete', $employee);
    }

    public function terminate(User $user, Employee $employee): bool
    {
        return $this->canOn($user, 'employees.update', $employee);
    }

    public function reactivate(User $user, Employee $employee): bool
    {
        return $this->canOn($user, 'employees.update', $employee);
    }

    public function uploadPhoto(User $user, Employee $employee): bool
    {
        return $this->canOn($user, 'employees.update', $employee);
    }
}
