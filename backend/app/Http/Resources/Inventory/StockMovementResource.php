<?php

namespace App\Http\Resources\Inventory;

use App\Models\Inventory\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StockMovement */
class StockMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', fn () => ['id' => $this->product->id, 'sku' => $this->product->sku, 'name' => $this->product->name]),
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->whenLoaded('warehouse', fn () => ['id' => $this->warehouse->id, 'name' => $this->warehouse->name, 'code' => $this->warehouse->code]),
            'location_id' => $this->location_id,
            'batch_id' => $this->batch_id,
            'batch' => $this->whenLoaded('batch', fn () => $this->batch ? ['id' => $this->batch->id, 'batch_number' => $this->batch->batch_number, 'expires_at' => $this->batch->expires_at?->toDateString()] : null),
            'type' => $this->type,
            'quantity' => $this->quantity,
            'unit_cost' => $this->unit_cost,
            'total_cost' => $this->total_cost,
            'balance_after' => $this->balance_after,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'reason' => $this->reason,
            'moved_at' => $this->moved_at?->toJSON(),
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? ['id' => $this->creator->id, 'name' => $this->creator->name] : null),
            'created_at' => $this->created_at?->toJSON(),
        ];
    }
}
