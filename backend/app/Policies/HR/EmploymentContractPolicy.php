<?php

namespace App\Policies\HR;

use App\Models\HR\EmploymentContract;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class EmploymentContractPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'employees.view');
    }

    public function view(User $user, EmploymentContract $contract): bool
    {
        return $this->canOn($user, 'employees.view', $contract);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'employees.update');
    }

    public function update(User $user, EmploymentContract $contract): bool
    {
        return $this->canOn($user, 'employees.update', $contract);
    }

    public function delete(User $user, EmploymentContract $contract): bool
    {
        return $this->canOn($user, 'employees.update', $contract);
    }

    /** Contract files are personnel documents: employees.view AND employees.documents.view. */
    public function downloadDocument(User $user, EmploymentContract $contract): bool
    {
        return $this->canOn($user, 'employees.view', $contract) && $this->can($user, 'employees.documents.view');
    }
}
