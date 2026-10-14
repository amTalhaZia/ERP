<?php

namespace App\Policies\Projects;

use App\Models\Projects\Project;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;
use App\Policies\Projects\Concerns\ChecksProjectAccess;

class ProjectPolicy
{
    use ChecksPermissions, ChecksProjectAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['projects.view', 'projects.manage', 'tasks.view']);
    }

    public function view(User $user, Project $project): bool
    {
        return $this->sameTenant($project)
            && $this->branchAccessible($project->branch_id)
            && $this->canSeeProject($user, $project->id);
    }

    /** Budget, expenses and cost figures: project readers only (not plain members). */
    public function viewFinancials(User $user, Project $project): bool
    {
        return $this->sameTenant($project) && $user->hasAnyPermission(['projects.view', 'projects.manage']);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'projects.manage');
    }

    public function update(User $user, Project $project): bool
    {
        return $this->canOn($user, 'projects.manage', $project);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->canOn($user, 'projects.manage', $project);
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $this->canOn($user, 'projects.manage', $project);
    }

    /** Create tasks inside the project. */
    public function createTask(User $user, Project $project): bool
    {
        return $this->sameTenant($project) && $this->canWorkOnProject($user, $project->id);
    }
}
