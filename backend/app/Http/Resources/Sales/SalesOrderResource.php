<?php

namespace App\Http\Resources\Sales;

use App\Http\Resources\Sales\Concerns\PresentsUsers;
use App\Models\Sales\SalesOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SalesOrder */
class SalesOrderResource extends JsonResource
{
    use PresentsUsers;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'number' => $this->number,
            'customer_id' => $this->customer_id,
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'code' => $this->customer->code,
            ]),
            'quotation_id' => $this->quotation_id,
            'order_date' => $this->order_date?->toDateString(),
            'expected_delivery_date' => $this->expected_delivery_date?->toDateString(),
            'status' => $this->status?->value,
            'currency' => $this->currency,
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'tax_total' => $this->tax_total,
            'total' => $this->total,
            'shipping_address' => [
                'name' => $this->shipping_name,
                'line1' => $this->shipping_address_line1,
                'line2' => $this->shipping_address_line2,
                'city' => $this->shipping_city,
                'state' => $this->shipping_state,
                'postal_code' => $this->shipping_postal_code,
                'country' => $this->shipping_country,
            ],
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->whenLoaded('warehouse', fn () => $this->warehouse ? ['id' => $this->warehouse->id, 'name' => $this->warehouse->name] : null),
            'notes' => $this->notes,
            'terms' => $this->terms,
            'sales_rep_id' => $this->sales_rep_id,
            'sales_rep' => $this->userSummary('salesRep'),
            'created_by' => $this->created_by,
            'confirmed_at' => $this->confirmed_at?->toJSON(),
            'approved_by' => $this->approved_by,
            'approver' => $this->userSummary('approver'),
            'approved_at' => $this->approved_at?->toJSON(),
            'cancelled_reason' => $this->cancelled_reason,
            'cancelled_at' => $this->cancelled_at?->toJSON(),
            'lines' => DocumentLineResource::collection($this->whenLoaded('lines')),
            'lines_count' => $this->whenCounted('lines'),
            'deliveries' => DeliveryResource::collection($this->whenLoaded('deliveries')),
            'deliveries_count' => $this->whenCounted('deliveries'),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
