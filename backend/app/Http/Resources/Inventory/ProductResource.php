<?php

namespace App\Http\Resources\Inventory;

use App\Models\Inventory\Product;
use App\Support\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `quantity_on_hand` is present only when the query aggregated it with
 * `withSum('stockLevels as quantity_on_hand', 'quantity_on_hand')`.
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => $this->category ? ['id' => $this->category->id, 'name' => $this->category->name] : null),
            'uom_id' => $this->uom_id,
            'uom' => $this->whenLoaded('uom', fn () => $this->uom ? ['id' => $this->uom->id, 'name' => $this->uom->name, 'symbol' => $this->uom->symbol] : null),
            'cost_price' => $this->cost_price,
            'sale_price' => $this->sale_price,
            'currency' => $this->currency,
            'tax_rate' => $this->tax_rate,
            'reorder_level' => $this->reorder_level,
            'reorder_quantity' => $this->reorder_quantity,
            'track_batches' => $this->track_batches,
            'track_serials' => $this->track_serials,
            'is_active' => $this->is_active,
            'image_url' => $this->imageUrl(),
            'attributes' => $this->resource->getAttribute('attributes'),
            'quantity_on_hand' => $this->whenHas('quantity_on_hand', fn () => Money::normalize($this->getAttribute('quantity_on_hand'))),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
