<?php

namespace App\Http\Resources\Procurement;

use App\Models\Procurement\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PurchaseOrder */
class PurchaseOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'number' => $this->number,
            'status' => $this->status?->value,
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->whenLoaded('supplier', fn () => $this->supplier ? ['id' => $this->supplier->id, 'code' => $this->supplier->code, 'name' => $this->supplier->name] : null),
            'requisition_id' => $this->requisition_id,
            'requisition' => $this->whenLoaded('requisition', fn () => $this->requisition ? ['id' => $this->requisition->id, 'number' => $this->requisition->number] : null),
            'branch_id' => $this->branch_id,
            'order_date' => $this->order_date?->toDateString(),
            'expected_date' => $this->expected_date?->toDateString(),
            'currency' => $this->currency,
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'tax_total' => $this->tax_total,
            'shipping_cost' => $this->shipping_cost,
            'total' => $this->total,
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->whenLoaded('warehouse', fn () => $this->warehouse ? ['id' => $this->warehouse->id, 'name' => $this->warehouse->name] : null),
            'delivery_address' => $this->delivery_address,
            'payment_terms_days' => $this->payment_terms_days,
            'notes' => $this->notes,
            'terms' => $this->terms,
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? ['id' => $this->creator->id, 'name' => $this->creator->name] : null),
            'submitted_at' => $this->submitted_at?->toJSON(),
            'approved_by' => $this->approved_by,
            'approver' => $this->whenLoaded('approver', fn () => $this->approver ? ['id' => $this->approver->id, 'name' => $this->approver->name] : null),
            'approved_at' => $this->approved_at?->toJSON(),
            'sent_at' => $this->sent_at?->toJSON(),
            'closed_at' => $this->closed_at?->toJSON(),
            'cancelled_at' => $this->cancelled_at?->toJSON(),
            'cancelled_reason' => $this->cancelled_reason,
            'lines' => PurchaseOrderLineResource::collection($this->whenLoaded('lines')),
            'lines_count' => $this->whenCounted('lines'),
            'goods_receipts_count' => $this->whenCounted('goodsReceipts'),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
