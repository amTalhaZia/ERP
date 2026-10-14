<?php

namespace App\Http\Resources\Procurement;

use App\Models\Procurement\PurchaseReturn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PurchaseReturn */
class PurchaseReturnResource extends JsonResource
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
            'reason' => $this->reason?->value,
            'purchase_order_id' => $this->purchase_order_id,
            'purchase_order' => $this->whenLoaded('purchaseOrder', fn () => $this->purchaseOrder ? [
                'id' => $this->purchaseOrder->id,
                'number' => $this->purchaseOrder->number,
                'status' => $this->purchaseOrder->status?->value,
                'supplier_id' => $this->purchaseOrder->supplier_id,
            ] : null),
            'goods_receipt_id' => $this->goods_receipt_id,
            'goods_receipt' => $this->whenLoaded('goodsReceipt', fn () => $this->goodsReceipt ? ['id' => $this->goodsReceipt->id, 'number' => $this->goodsReceipt->number] : null),
            'return_date' => $this->return_date?->toDateString(),
            'notes' => $this->notes,
            'total' => $this->total,
            'created_by' => $this->created_by,
            'confirmed_at' => $this->confirmed_at?->toJSON(),
            'cancelled_at' => $this->cancelled_at?->toJSON(),
            'lines' => PurchaseReturnLineResource::collection($this->whenLoaded('lines')),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
