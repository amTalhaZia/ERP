<?php

namespace App\Actions\Procurement;

use App\Enums\Procurement\RequisitionStatus;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseRequisition;
use App\Models\Procurement\PurchaseRequisitionLine;
use App\Models\Procurement\Supplier;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Turns an approved requisition into a draft purchase order for one supplier,
 * copying the lines (estimated prices become unit prices) and marking the
 * requisition as Ordered.
 */
class CreatePurchaseOrderFromRequisitionAction
{
    public function __construct(
        private readonly SavePurchaseOrderAction $savePurchaseOrder,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $overrides  optional header fields (expected_date, warehouse_id, notes, …)
     */
    public function handle(PurchaseRequisition $requisition, Supplier $supplier, User $actor, array $overrides = []): PurchaseOrder
    {
        if ($requisition->status !== RequisitionStatus::Approved) {
            throw new ConflictHttpException('Only approved requisitions can be converted to a purchase order.');
        }

        return DB::transaction(function () use ($requisition, $supplier, $actor, $overrides): PurchaseOrder {
            $lines = $requisition->lines->map(fn (PurchaseRequisitionLine $line): array => [
                'product_id' => $line->product_id,
                'description' => $line->description,
                'quantity' => (string) $line->quantity,
                'uom' => $line->uom,
                'unit_price' => (string) $line->estimated_unit_price,
                'discount_percent' => 0,
                'tax_rate' => 0,
            ])->all();

            $order = $this->savePurchaseOrder->handle([
                'supplier_id' => $supplier->id,
                'requisition_id' => $requisition->id,
                'branch_id' => $requisition->branch_id,
                'order_date' => now()->toDateString(),
                'expected_date' => $requisition->needed_by?->toDateString(),
                'notes' => $requisition->justification,
                'lines' => $lines,
            ] + $overrides, $actor);

            $requisition->forceFill(['status' => RequisitionStatus::Ordered])->save();

            $this->audit->log('purchase.requisitions.ordered', $requisition, ['status' => RequisitionStatus::Approved->value], [
                'status' => RequisitionStatus::Ordered->value,
                'purchase_order_id' => $order->id,
                'purchase_order_number' => $order->number,
            ]);

            return $order;
        });
    }
}
