<?php

namespace App\Http\Resources\Inventory;

use App\Models\Inventory\StockAdjustment;
use App\Models\Inventory\StockAdjustmentLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StockAdjustment */
class StockAdjustmentResource extends JsonResource
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
            'status' => $this->status,
            'reason' => $this->reason,
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->whenLoaded('warehouse', fn () => ['id' => $this->warehouse->id, 'name' => $this->warehouse->name, 'code' => $this->warehouse->code]),
            'notes' => $this->notes,
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn (StockAdjustmentLine $line) => [
                'id' => $line->id,
                'product_id' => $line->product_id,
                'product' => $line->relationLoaded('product') && $line->product
                    ? ['id' => $line->product->id, 'sku' => $line->product->sku, 'name' => $line->product->name]
                    : null,
                'counted_quantity' => $line->counted_quantity,
                'system_quantity' => $line->system_quantity,
                'difference' => $line->difference,
                'unit_cost' => $line->unit_cost,
            ])->values()),
            'lines_count' => $this->whenCounted('lines'),
            'posted_at' => $this->posted_at?->toJSON(),
            'created_by' => $this->created_by,
            'posted_by' => $this->posted_by,
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
