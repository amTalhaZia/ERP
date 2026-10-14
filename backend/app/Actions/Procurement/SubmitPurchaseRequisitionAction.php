<?php

namespace App\Actions\Procurement;

use App\Enums\Procurement\RequisitionStatus;
use App\Models\Procurement\PurchaseRequisition;
use App\Models\User;
use App\Notifications\Procurement\RequisitionSubmitted;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Draft → Submitted. Notifies every active member of the company who holds
 * `requisitions.approve` (except the requester, who may not self-approve).
 */
class SubmitPurchaseRequisitionAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(PurchaseRequisition $requisition): PurchaseRequisition
    {
        if ($requisition->status !== RequisitionStatus::Draft) {
            throw new ConflictHttpException('Only draft requisitions can be submitted.');
        }

        if (! $requisition->lines()->exists()) {
            throw new ConflictHttpException('Add at least one line before submitting.');
        }

        DB::transaction(function () use ($requisition): void {
            $requisition->forceFill([
                'status' => RequisitionStatus::Submitted,
                'submitted_at' => now(),
                'rejection_reason' => null,
                'rejected_at' => null,
            ])->save();

            $this->audit->log('purchase.requisitions.submitted', $requisition, null, ['status' => RequisitionStatus::Submitted->value]);
        });

        $requisition->load('requester:id,name');

        Notification::send($this->approversFor($requisition), new RequisitionSubmitted($requisition));

        return $requisition;
    }

    /**
     * @return Collection<int, User>
     */
    private function approversFor(PurchaseRequisition $requisition): Collection
    {
        $companyId = (int) $requisition->company_id;

        return User::query()
            ->where('is_active', true)
            ->whereKeyNot($requisition->requester_id)
            ->whereHas('memberships', fn ($membership) => $membership->where('company_id', $companyId)->where('is_active', true))
            ->whereHas('roles', fn ($roles) => $roles
                ->where('roles.company_id', $companyId)
                ->where('role_user.company_id', $companyId)
                ->whereHas('permissions', fn ($permissions) => $permissions->where('slug', 'requisitions.approve')))
            ->get();
    }
}
