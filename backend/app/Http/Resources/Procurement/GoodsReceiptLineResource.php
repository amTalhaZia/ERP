<?php

namespace App\Http\Resources\Procurement;

use App\Models\Procurement\GoodsReceiptLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GoodsReceiptLine */
class GoodsReceiptLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_order_line_id' => $this->purchase_order_line_id,
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', fn () => $this->product ? [
                'id' => $this->product->id,
                'sku' => $this->product->sku,
                'name' => $this->product->name,
            ] : null),
            'description' => $this->description,
            'quantity_ordered' => $this->quantity_ordered,
            'quantity_received' => $this->quantity_received,
            'quantity_rejected' => $this->quantity_rejected,
            'rejection_reason' => $this->rejection_reason,
            'unit_cost' => $this->unit_cost,
            'batch_number' => $this->batch_number,
            'expires_at' => $this->expires_at?->toDateString(),
        ];
    }
}
