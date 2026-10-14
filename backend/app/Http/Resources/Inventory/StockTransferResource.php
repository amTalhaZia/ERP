<?php

namespace App\Http\Resources\Inventory;

use App\Models\Inventory\StockTransfer;
use App\Models\Inventory\StockTransferLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StockTransfer */
class StockTransferResource extends JsonResource
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
            'from_warehouse_id' => $this->from_warehouse_id,
            'from_warehouse' => $this->whenLoaded('fromWarehouse', fn () => ['id' => $this->fromWarehouse->id, 'name' => $this->fromWarehouse->name, 'code' => $this->fromWarehouse->code]),
            'to_warehouse_id' => $this->to_warehouse_id,
            'to_warehouse' => $this->whenLoaded('toWarehouse', fn () => ['id' => $this->toWarehouse->id, 'name' => $this->toWarehouse->name, 'code' => $this->toWarehouse->code]),
            'notes' => $this->notes,
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn (StockTransferLine $line) => [
                'id' => $line->id,
                'product_id' => $line->product_id,
                'product' => $line->relationLoaded('product') && $line->product
                    ? ['id' => $line->product->id, 'sku' => $line->product->sku, 'name' => $line->product->name]
                    : null,
                'quantity' => $line->quantity,
                'unit_cost' => $line->unit_cost,
            ])->values()),
            'lines_count' => $this->whenCounted('lines'),
            'shipped_at' => $this->shipped_at?->toJSON(),
            'received_at' => $this->received_at?->toJSON(),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
