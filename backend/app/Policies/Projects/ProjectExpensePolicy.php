<?php

namespace App\Policies\Projects;

use App\Models\Projects\ProjectExpense;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class ProjectExpensePolicy
{
    use ChecksPermissions;

    public function view(User $user, ProjectExpense $expense): bool
    {
        return $this->sameTenant($expense) && $user->hasAnyPermission(['projects.view', 'projects.manage']);
    }

    public function update(User $user, ProjectExpense $expense): bool
    {
        return $this->canOn($user, 'projects.manage', $expense);
    }

    public function delete(User $user, ProjectExpense $expense): bool
    {
        return $this->canOn($user, 'projects.manage', $expense);
    }
}
