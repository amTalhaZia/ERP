<?php

namespace App\Http\Resources\Procurement;

use App\Models\Procurement\PurchaseRequisitionLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PurchaseRequisitionLine */
class PurchaseRequisitionLineResource extends JsonResource
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
            'estimated_unit_price' => $this->estimated_unit_price,
            'line_total' => $this->line_total,
        ];
    }
}
