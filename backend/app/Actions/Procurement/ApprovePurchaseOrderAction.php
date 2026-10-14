<?php

namespace App\Actions\Procurement;

use App\Enums\Procurement\PurchaseOrderStatus;
use App\Events\Procurement\PurchaseOrderApproved;
use App\Models\Procurement\PurchaseOrder;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Submitted → Approved. Approval thresholds (e.g. a per-company
 * `settings.procurement.po_approval_limit`) are a planned extension; today the
 * `purchase_orders.approve` permission is the only gate.
 */
class ApprovePurchaseOrderAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(PurchaseOrder $order, User $approver): PurchaseOrder
    {
        if ($order->status !== PurchaseOrderStatus::Submitted) {
            throw new ConflictHttpException('Only submitted purchase orders can be approved.');
        }

        return DB::transaction(function () use ($order, $approver): PurchaseOrder {
            $order->forceFill([
                'status' => PurchaseOrderStatus::Approved,
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ])->save();

            $this->audit->log('purchase.orders.approved', $order, ['status' => PurchaseOrderStatus::Submitted->value], [
                'status' => PurchaseOrderStatus::Approved->value,
                'approved_by' => $approver->id,
                'total' => (string) $order->total,
            ]);

            $order->load(['lines', 'supplier']);

            PurchaseOrderApproved::dispatch($order);

            return $order;
        });
    }
}
