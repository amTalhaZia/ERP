<?php

namespace App\Actions\Procurement;

use App\Enums\Procurement\PurchaseOrderStatus;
use App\Enums\Procurement\RequisitionStatus;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderLine;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Cancels an order that has not received any goods. If it was the only open
 * order for a requisition, the requisition is returned to Approved so it can
 * be re-ordered.
 */
class CancelPurchaseOrderAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(PurchaseOrder $order, string $reason): PurchaseOrder
    {
        if ($order->status->isFinal()) {
            throw new ConflictHttpException('This purchase order is already closed or cancelled.');
        }

        $received = $order->lines()->get()->contains(
            fn (PurchaseOrderLine $line) => Money::compare((string) $line->quantity_received, '0') > 0,
        );

        if ($received) {
            throw new ConflictHttpException('Goods have already been received against this order; close it instead.');
        }

        return DB::transaction(function () use ($order, $reason): PurchaseOrder {
            $before = $order->status->value;

            $order->forceFill([
                'status' => PurchaseOrderStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_reason' => $reason,
            ])->save();

            if ($order->requisition_id !== null) {
                $requisition = $order->requisition()->first();
                $openOrders = PurchaseOrder::query()
                    ->where('requisition_id', $order->requisition_id)
                    ->where('status', '!=', PurchaseOrderStatus::Cancelled->value)
                    ->exists();

                if ($requisition !== null && $requisition->status === RequisitionStatus::Ordered && ! $openOrders) {
                    $requisition->forceFill(['status' => RequisitionStatus::Approved])->save();
                }
            }

            $this->audit->log('purchase.orders.cancelled', $order, ['status' => $before], [
                'status' => PurchaseOrderStatus::Cancelled->value,
                'cancelled_reason' => $reason,
            ]);

            return $order;
        });
    }
}
