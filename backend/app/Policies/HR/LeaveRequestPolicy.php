<?php

namespace App\Policies\HR;

use App\Models\HR\LeaveRequest;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;
use App\Policies\HR\Concerns\ResolvesOwnEmployee;

class LeaveRequestPolicy
{
    use ChecksPermissions, ResolvesOwnEmployee;

    /** Anyone who can see all requests, or who may submit their own (the controller narrows the list). */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['leave.view', 'leave.request', 'leave.approve', 'leave.manage']);
    }

    public function viewAll(User $user): bool
    {
        return $user->hasAnyPermission(['leave.view', 'leave.approve', 'leave.manage']);
    }

    public function view(User $user, LeaveRequest $leaveRequest): bool
    {
        if (! $this->sameTenant($leaveRequest)) {
            return false;
        }

        return $this->viewAll($user) || $this->ownsEmployee($user, $leaveRequest->employee_id);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'leave.request');
    }

    /** Approvers may never decide their own request. */
    public function approve(User $user, LeaveRequest $leaveRequest): bool
    {
        return $this->canOn($user, 'leave.approve', $leaveRequest)
            && ! $this->ownsEmployee($user, $leaveRequest->employee_id);
    }

    public function reject(User $user, LeaveRequest $leaveRequest): bool
    {
        return $this->approve($user, $leaveRequest);
    }

    public function cancel(User $user, LeaveRequest $leaveRequest): bool
    {
        if (! $this->sameTenant($leaveRequest)) {
            return false;
        }

        return $this->ownsEmployee($user, $leaveRequest->employee_id) || $this->can($user, 'leave.manage');
    }

    /** The requester, or anyone who may see all requests (leave.view / approve / manage). */
    public function downloadAttachment(User $user, LeaveRequest $leaveRequest): bool
    {
        return $this->view($user, $leaveRequest);
    }
}
