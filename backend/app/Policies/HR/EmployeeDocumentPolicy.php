<?php

namespace App\Policies\HR;

use App\Models\HR\Employee;
use App\Models\HR\EmployeeDocument;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;
use App\Policies\HR\Concerns\ResolvesOwnEmployee;

class EmployeeDocumentPolicy
{
    use ChecksPermissions, ResolvesOwnEmployee;

    /** List an employee's documents: HR document viewers, or the employee themself. */
    public function viewAny(User $user, Employee $employee): bool
    {
        return $this->canOn($user, 'employees.documents.view', $employee)
            || ($this->sameTenant($employee) && $employee->user_id === $user->id);
    }

    public function viewAll(User $user): bool
    {
        return $this->can($user, 'employees.documents.view');
    }

    /** Download: permission holders, or the owner for non-confidential files only. */
    public function download(User $user, EmployeeDocument $document): bool
    {
        if (! $this->sameTenant($document)) {
            return false;
        }

        if ($this->can($user, 'employees.documents.view')) {
            return true;
        }

        return ! $document->is_confidential && $this->ownsEmployee($user, $document->employee_id);
    }

    public function create(User $user, Employee $employee): bool
    {
        return $this->canOn($user, 'employees.update', $employee);
    }

    public function delete(User $user, EmployeeDocument $document): bool
    {
        return $this->canOn($user, 'employees.update', $document);
    }
}
