<?php

namespace App\Policies\Projects;

use App\Models\Projects\Task;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;
use App\Policies\Projects\Concerns\ChecksProjectAccess;

class TaskPolicy
{
    use ChecksPermissions, ChecksProjectAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['tasks.view', 'projects.view', 'projects.manage']);
    }

    public function view(User $user, Task $task): bool
    {
        return $this->sameTenant($task) && $this->canSeeProject($user, $task->project_id);
    }

    /** Edit, move, add subtasks: projects.manage, or tasks.manage + project membership. */
    public function update(User $user, Task $task): bool
    {
        return $this->sameTenant($task) && $this->canWorkOnProject($user, $task->project_id);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    /** Anyone who can see the task can discuss it. */
    public function comment(User $user, Task $task): bool
    {
        return $this->view($user, $task);
    }
}
