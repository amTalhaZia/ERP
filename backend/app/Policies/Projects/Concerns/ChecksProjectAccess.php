<?php

namespace App\Policies\Projects\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Project-level access rules shared by the Projects policies:
 *  - see a project: `projects.view`, or `tasks.view` + membership;
 *  - work on its tasks: `projects.manage`, or `tasks.manage` + membership.
 */
trait ChecksProjectAccess
{
    /** @var array<string, bool> */
    private array $membershipCache = [];

    protected function isProjectMember(User $user, int $projectId): bool
    {
        return $this->membershipCache["{$projectId}:{$user->id}"] ??= DB::table('project_members')
            ->where('project_id', $projectId)
            ->where('user_id', $user->id)
            ->exists();
    }

    protected function canSeeProject(User $user, int $projectId): bool
    {
        return $user->hasPermission('projects.view')
            || $user->hasPermission('projects.manage')
            || ($user->hasPermission('tasks.view') && $this->isProjectMember($user, $projectId));
    }

    protected function canWorkOnProject(User $user, int $projectId): bool
    {
        return $user->hasPermission('projects.manage')
            || ($user->hasPermission('tasks.manage') && $this->isProjectMember($user, $projectId));
    }
}
