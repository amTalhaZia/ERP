<?php

namespace App\Http\Resources\Sales;

use App\Http\Resources\Sales\Concerns\PresentsUsers;
use App\Models\Sales\Quotation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Quotation */
class QuotationResource extends JsonResource
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
            'date' => $this->date?->toDateString(),
            'valid_until' => $this->valid_until?->toDateString(),
            'status' => $this->status?->value,
            'currency' => $this->currency,
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'tax_total' => $this->tax_total,
            'total' => $this->total,
            'notes' => $this->notes,
            'terms' => $this->terms,
            'rejection_reason' => $this->rejection_reason,
            'sales_rep_id' => $this->sales_rep_id,
            'sales_rep' => $this->userSummary('salesRep'),
            'created_by' => $this->created_by,
            'converted_sales_order_id' => $this->converted_sales_order_id,
            'lines' => DocumentLineResource::collection($this->whenLoaded('lines')),
            'lines_count' => $this->whenCounted('lines'),
            'sent_at' => $this->sent_at?->toJSON(),
            'accepted_at' => $this->accepted_at?->toJSON(),
            'rejected_at' => $this->rejected_at?->toJSON(),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
