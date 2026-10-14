<?php

namespace App\Policies;

use App\Models\CompanyUser;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

/**
 * Abilities are keyed on the *membership* (CompanyUser) rather than the User
 * record, so a user who exists in several companies is only ever managed
 * through the membership of the active tenant.
 */
class CompanyUserPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'users.view');
    }

    public function view(User $user, CompanyUser $membership): bool
    {
        return $this->canOn($user, 'users.view', $membership);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'users.manage');
    }

    public function update(User $user, CompanyUser $membership): bool
    {
        return $this->canOn($user, 'users.manage', $membership);
    }

    /** A user can never remove their own membership. */
    public function delete(User $user, CompanyUser $membership): bool
    {
        return $this->canOn($user, 'users.manage', $membership) && $membership->user_id !== $user->id;
    }
}
