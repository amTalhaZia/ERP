<?php

namespace App\Http\Resources\Inventory;

use App\Models\Inventory\StockLevel;
use App\Support\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StockLevel */
class StockLevelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'sku' => $this->product->sku,
                'name' => $this->product->name,
                'reorder_level' => $this->product->reorder_level,
                'uom' => $this->product->relationLoaded('uom') && $this->product->uom
                    ? ['id' => $this->product->uom->id, 'name' => $this->product->uom->name, 'symbol' => $this->product->uom->symbol]
                    : null,
            ]),
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->whenLoaded('warehouse', fn () => ['id' => $this->warehouse->id, 'name' => $this->warehouse->name, 'code' => $this->warehouse->code]),
            'location_id' => $this->location_id,
            'location' => $this->whenLoaded('location', fn () => $this->location ? ['id' => $this->location->id, 'code' => $this->location->code, 'name' => $this->location->name] : null),
            'quantity_on_hand' => $this->quantity_on_hand,
            'quantity_reserved' => $this->quantity_reserved,
            'quantity_available' => Money::sub(Money::normalize($this->quantity_on_hand), Money::normalize($this->quantity_reserved)),
            'average_cost' => $this->average_cost,
            'stock_value' => Money::mul(Money::normalize($this->quantity_on_hand), Money::normalize($this->average_cost)),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
