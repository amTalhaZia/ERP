<?php

namespace App\Actions\Procurement;

use App\Enums\Procurement\RequisitionStatus;
use App\Models\Procurement\PurchaseRequisition;
use App\Models\User;
use App\Notifications\Procurement\RequisitionDecided;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Submitted → Approved | Rejected. Records who decided and when, writes an
 * explicit audit row and notifies the requester.
 */
class DecidePurchaseRequisitionAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function approve(PurchaseRequisition $requisition, User $approver): PurchaseRequisition
    {
        return $this->decide($requisition, $approver, RequisitionStatus::Approved, null);
    }

    public function reject(PurchaseRequisition $requisition, User $approver, string $reason): PurchaseRequisition
    {
        return $this->decide($requisition, $approver, RequisitionStatus::Rejected, $reason);
    }

    private function decide(PurchaseRequisition $requisition, User $approver, RequisitionStatus $status, ?string $reason): PurchaseRequisition
    {
        if ($requisition->status !== RequisitionStatus::Submitted) {
            throw new ConflictHttpException('Only submitted requisitions can be approved or rejected.');
        }

        DB::transaction(function () use ($requisition, $approver, $status, $reason): void {
            $before = ['status' => $requisition->status->value];

            $requisition->forceFill([
                'status' => $status,
                'approver_id' => $approver->id,
                'approved_at' => $status === RequisitionStatus::Approved ? now() : null,
                'rejected_at' => $status === RequisitionStatus::Rejected ? now() : null,
                'rejection_reason' => $reason,
            ])->save();

            $this->audit->log(
                $status === RequisitionStatus::Approved ? 'purchase.requisitions.approved' : 'purchase.requisitions.rejected',
                $requisition,
                $before,
                ['status' => $status->value, 'approver_id' => $approver->id, 'rejection_reason' => $reason],
            );
        });

        $requisition->load(['requester', 'approver:id,name']);
        $requisition->requester?->notify(new RequisitionDecided($requisition));

        return $requisition;
    }
}
