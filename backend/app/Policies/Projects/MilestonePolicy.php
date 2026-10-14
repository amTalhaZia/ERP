<?php

namespace App\Policies\Projects;

use App\Models\Projects\Milestone;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;
use App\Policies\Projects\Concerns\ChecksProjectAccess;

class MilestonePolicy
{
    use ChecksPermissions, ChecksProjectAccess;

    public function view(User $user, Milestone $milestone): bool
    {
        return $this->sameTenant($milestone) && $this->canSeeProject($user, $milestone->project_id);
    }

    public function update(User $user, Milestone $milestone): bool
    {
        return $this->canOn($user, 'projects.manage', $milestone);
    }

    public function delete(User $user, Milestone $milestone): bool
    {
        return $this->canOn($user, 'projects.manage', $milestone);
    }
}
