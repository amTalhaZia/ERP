<?php

namespace App\Policies\Procurement;

use App\Models\Procurement\PurchaseRequisition;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

/**
 * Requisitions have two audiences: requesters (requisitions.create) who only
 * see and edit their own documents, and reviewers (requisitions.view /
 * requisitions.approve) who see everything. State checks (draft-only edits,
 * approve only when submitted) live in the controller/actions as 409s.
 */
class PurchaseRequisitionPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['requisitions.view', 'requisitions.create', 'requisitions.approve']);
    }

    public function view(User $user, PurchaseRequisition $requisition): bool
    {
        if (! $this->sameTenant($requisition)) {
            return false;
        }

        return ($user->hasAnyPermission(['requisitions.view', 'requisitions.approve']) && $this->inAllowedBranch($requisition))
            || ($this->isRequester($user, $requisition) && $this->can($user, 'requisitions.create'));
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'requisitions.create');
    }

    public function update(User $user, PurchaseRequisition $requisition): bool
    {
        return $this->canOn($user, 'requisitions.create', $requisition) && $this->isRequester($user, $requisition);
    }

    public function delete(User $user, PurchaseRequisition $requisition): bool
    {
        return $this->update($user, $requisition);
    }

    public function submit(User $user, PurchaseRequisition $requisition): bool
    {
        return $this->update($user, $requisition);
    }

    public function cancel(User $user, PurchaseRequisition $requisition): bool
    {
        return $this->update($user, $requisition);
    }

    /** Approvers may never decide on their own requests. */
    public function approve(User $user, PurchaseRequisition $requisition): bool
    {
        return $this->canOn($user, 'requisitions.approve', $requisition) && ! $this->isRequester($user, $requisition);
    }

    public function reject(User $user, PurchaseRequisition $requisition): bool
    {
        return $this->approve($user, $requisition);
    }

    public function createPurchaseOrder(User $user, PurchaseRequisition $requisition): bool
    {
        return $this->canOn($user, 'purchase_orders.manage', $requisition);
    }

    private function isRequester(User $user, PurchaseRequisition $requisition): bool
    {
        return (int) $requisition->requester_id === (int) $user->getKey();
    }
}
