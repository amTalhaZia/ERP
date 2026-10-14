<?php

namespace App\Policies\Payroll;

use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared rules for payroll configuration (pay components, tax tables, salary
 * structures, employee salaries): read with payroll.view or payroll.manage,
 * write with payroll.manage. Concrete policies extend this per model.
 */
abstract class PayrollSetupPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['payroll.view', 'payroll.manage']);
    }

    public function view(User $user, Model $model): bool
    {
        return $this->sameTenant($model) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'payroll.manage');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->canOn($user, 'payroll.manage', $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->canOn($user, 'payroll.manage', $model);
    }
}
