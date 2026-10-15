<?php

namespace App\Policies\HR;

use App\Models\HR\PerformanceReview;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;
use App\Policies\HR\Concerns\ResolvesOwnEmployee;

class PerformanceReviewPolicy
{
    use ChecksPermissions, ResolvesOwnEmployee;

    /** Every member may list (the controller narrows to own/reviewed rows without performance.view). */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function viewAll(User $user): bool
    {
        return $user->hasAnyPermission(['performance.view', 'performance.manage']);
    }

    public function view(User $user, PerformanceReview $review): bool
    {
        if (! $this->sameTenant($review)) {
            return false;
        }

        return ($this->viewAll($user) && $this->inAllowedBranch($review))
            || $review->reviewer_id === $user->id
            || $this->ownsEmployee($user, $review->employee_id);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'performance.manage');
    }

    public function update(User $user, PerformanceReview $review): bool
    {
        return $this->canOn($user, 'performance.manage', $review);
    }

    public function delete(User $user, PerformanceReview $review): bool
    {
        return $this->canOn($user, 'performance.manage', $review);
    }

    public function submit(User $user, PerformanceReview $review): bool
    {
        return $this->canOn($user, 'performance.manage', $review)
            || ($this->sameTenant($review) && $review->reviewer_id === $user->id);
    }

    /** Only the reviewed employee's own login may acknowledge. */
    public function acknowledge(User $user, PerformanceReview $review): bool
    {
        return $this->sameTenant($review) && $this->ownsEmployee($user, $review->employee_id);
    }
}
