<?php

namespace App\Http\Resources\Procurement;

use App\Models\Procurement\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Supplier */
class SupplierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type?->value,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'tax_number' => $this->tax_number,
            'currency' => $this->currency,
            'payment_terms_days' => $this->payment_terms_days,
            'credit_limit' => $this->credit_limit,
            'address' => [
                'line1' => $this->address_line1,
                'line2' => $this->address_line2,
                'city' => $this->city,
                'state' => $this->state,
                'postal_code' => $this->postal_code,
                'country' => $this->country,
            ],
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'bank_name' => $this->bank_name,
            // The account number itself is encrypted at rest and never returned.
            'bank_account_last4' => $this->bank_account_last4,
            'rating' => $this->rating,
            'lead_time_days' => $this->lead_time_days,
            'categories' => $this->categories ?? [],
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'is_approved' => $this->is_approved,
            'approved_at' => $this->approved_at?->toJSON(),
            'approver' => $this->whenLoaded('approver', fn () => $this->approver ? ['id' => $this->approver->id, 'name' => $this->approver->name] : null),
            'contacts' => SupplierContactResource::collection($this->whenLoaded('contacts')),
            'contacts_count' => $this->whenCounted('contacts'),
            'purchase_orders_count' => $this->whenCounted('purchaseOrders'),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
