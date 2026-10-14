<?php

namespace App\Http\Resources\Procurement;

use App\Models\Procurement\GoodsReceipt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GoodsReceipt */
class GoodsReceiptResource extends JsonResource
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
            'purchase_order_id' => $this->purchase_order_id,
            'purchase_order' => $this->whenLoaded('purchaseOrder', fn () => $this->purchaseOrder ? [
                'id' => $this->purchaseOrder->id,
                'number' => $this->purchaseOrder->number,
                'status' => $this->purchaseOrder->status?->value,
                'supplier_id' => $this->purchaseOrder->supplier_id,
            ] : null),
            'received_date' => $this->received_date?->toDateString(),
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->whenLoaded('warehouse', fn () => $this->warehouse ? ['id' => $this->warehouse->id, 'name' => $this->warehouse->name] : null),
            'received_by' => $this->received_by,
            'receiver' => $this->whenLoaded('receiver', fn () => $this->receiver ? ['id' => $this->receiver->id, 'name' => $this->receiver->name] : null),
            'supplier_delivery_note' => $this->supplier_delivery_note,
            'notes' => $this->notes,
            'confirmed_at' => $this->confirmed_at?->toJSON(),
            'cancelled_at' => $this->cancelled_at?->toJSON(),
            'lines' => GoodsReceiptLineResource::collection($this->whenLoaded('lines')),
            'lines_count' => $this->whenCounted('lines'),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
