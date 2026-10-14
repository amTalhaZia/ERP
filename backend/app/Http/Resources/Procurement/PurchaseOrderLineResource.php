<?php

namespace App\Http\Resources\Procurement;

use App\Models\Procurement\PurchaseOrderLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PurchaseOrderLine */
class PurchaseOrderLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'line_no' => $this->line_no,
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', fn () => $this->product ? [
                'id' => $this->product->id,
                'sku' => $this->product->sku,
                'name' => $this->product->name,
            ] : null),
            'description' => $this->description,
            'quantity' => $this->quantity,
            'uom' => $this->uom,
            'unit_price' => $this->unit_price,
            'discount_percent' => $this->discount_percent,
            'tax_rate' => $this->tax_rate,
            'line_subtotal' => $this->line_subtotal,
            'tax_amount' => $this->tax_amount,
            'line_total' => $this->line_total,
            'quantity_received' => $this->quantity_received,
            'quantity_returned' => $this->quantity_returned,
            'quantity_remaining' => $this->quantityRemaining(),
        ];
    }
}
