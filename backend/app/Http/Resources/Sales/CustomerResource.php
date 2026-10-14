<?php

namespace App\Http\Resources\Sales;

use App\Http\Resources\Sales\Concerns\PresentsUsers;
use App\Models\Sales\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Customer */
class CustomerResource extends JsonResource
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
            'billing_address' => [
                'line1' => $this->billing_address_line1,
                'line2' => $this->billing_address_line2,
                'city' => $this->billing_city,
                'state' => $this->billing_state,
                'postal_code' => $this->billing_postal_code,
                'country' => $this->billing_country,
            ],
            'shipping_address' => [
                'line1' => $this->shipping_address_line1,
                'line2' => $this->shipping_address_line2,
                'city' => $this->shipping_city,
                'state' => $this->shipping_state,
                'postal_code' => $this->shipping_postal_code,
                'country' => $this->shipping_country,
            ],
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'sales_rep_id' => $this->sales_rep_id,
            'sales_rep' => $this->userSummary('salesRep'),
            'source' => $this->source,
            'tags' => $this->tags ?? [],
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'contacts' => CustomerContactResource::collection($this->whenLoaded('contacts')),
            'contacts_count' => $this->whenCounted('contacts'),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
