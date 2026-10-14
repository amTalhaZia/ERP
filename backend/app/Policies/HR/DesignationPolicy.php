<?php

namespace App\Policies\HR;

use App\Models\HR\Designation;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class DesignationPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'employees.view');
    }

    public function view(User $user, Designation $designation): bool
    {
        return $this->canOn($user, 'employees.view', $designation);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'employees.update');
    }

    public function update(User $user, Designation $designation): bool
    {
        return $this->canOn($user, 'employees.update', $designation);
    }

    public function delete(User $user, Designation $designation): bool
    {
        return $this->canOn($user, 'employees.update', $designation);
    }
}
