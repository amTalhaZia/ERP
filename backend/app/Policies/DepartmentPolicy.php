<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class DepartmentPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'departments.view');
    }

    public function view(User $user, Department $department): bool
    {
        return $this->canOn($user, 'departments.view', $department);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'departments.manage');
    }

    public function update(User $user, Department $department): bool
    {
        return $this->canOn($user, 'departments.manage', $department);
    }

    public function delete(User $user, Department $department): bool
    {
        return $this->canOn($user, 'departments.manage', $department);
    }
}
