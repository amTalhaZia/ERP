<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class BranchPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'branches.view');
    }

    public function view(User $user, Branch $branch): bool
    {
        return $this->canOn($user, 'branches.view', $branch);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'branches.manage');
    }

    public function update(User $user, Branch $branch): bool
    {
        return $this->canOn($user, 'branches.manage', $branch);
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $this->canOn($user, 'branches.manage', $branch) && ! $branch->is_head_office;
    }
}
