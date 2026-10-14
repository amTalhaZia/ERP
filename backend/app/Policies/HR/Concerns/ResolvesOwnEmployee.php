<?php

namespace App\Policies\HR\Concerns;

use App\Models\HR\Employee;
use App\Models\User;

/**
 * Self-service helper: does the given employee row belong to the acting user?
 * Uses a query instead of a relation so policies never trigger lazy loading.
 */
trait ResolvesOwnEmployee
{
    protected function ownsEmployee(User $user, ?int $employeeId): bool
    {
        if ($employeeId === null) {
            return false;
        }

        return Employee::query()->whereKey($employeeId)->where('user_id', $user->id)->exists();
    }
}
