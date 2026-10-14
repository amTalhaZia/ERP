<?php

namespace App\Http\Resources\Sales;

use App\Models\Sales\Delivery;
use App\Models\Sales\DeliveryLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Delivery */
class DeliveryResource extends JsonResource
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
            'sales_order_id' => $this->sales_order_id,
            'sales_order' => $this->whenLoaded('salesOrder', fn () => [
                'id' => $this->salesOrder->id,
                'number' => $this->salesOrder->number,
                'status' => $this->salesOrder->status?->value,
                'customer_id' => $this->salesOrder->customer_id,
            ]),
            'customer' => $this->when(
                $this->relationLoaded('salesOrder') && $this->salesOrder?->relationLoaded('customer'),
                fn () => $this->salesOrder->customer ? ['id' => $this->salesOrder->customer->id, 'name' => $this->salesOrder->customer->name] : null,
            ),
            'delivery_date' => $this->delivery_date?->toDateString(),
            'status' => $this->status?->value,
            // Tracking is manual data entry (carrier + number); there is no live GPS feed.
            'tracking_mode' => 'manual',
            'carrier' => $this->carrier,
            'tracking_number' => $this->tracking_number,
            'shipped_at' => $this->shipped_at?->toJSON(),
            'delivered_at' => $this->delivered_at?->toJSON(),
            'cancelled_at' => $this->cancelled_at?->toJSON(),
            'notes' => $this->notes,
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn (DeliveryLine $line) => [
                'id' => $line->id,
                'sales_order_line_id' => $line->sales_order_line_id,
                'quantity' => $line->quantity,
                'description' => $line->relationLoaded('salesOrderLine') ? $line->salesOrderLine?->description : null,
                'product_id' => $line->relationLoaded('salesOrderLine') ? $line->salesOrderLine?->product_id : null,
                'uom' => $line->relationLoaded('salesOrderLine') ? $line->salesOrderLine?->uom : null,
                'product' => $line->relationLoaded('salesOrderLine') && $line->salesOrderLine?->relationLoaded('product') && $line->salesOrderLine->product
                    ? ['id' => $line->salesOrderLine->product->id, 'sku' => $line->salesOrderLine->product->sku, 'name' => $line->salesOrderLine->product->name]
                    : null,
            ])->all()),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
