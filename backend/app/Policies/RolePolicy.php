<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

/**
 * Roles are not tenant-scoped automatically (platform roles have company_id
 * NULL) but every role carries company_id, so `sameTenant()` still guards
 * record-level abilities.
 */
class RolePolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $this->canOn($user, 'roles.view', $role);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'roles.manage');
    }

    public function update(User $user, Role $role): bool
    {
        return $this->canOn($user, 'roles.manage', $role);
    }

    public function delete(User $user, Role $role): bool
    {
        return $this->canOn($user, 'roles.manage', $role);
    }
}
