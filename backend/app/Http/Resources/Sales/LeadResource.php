<?php

namespace App\Http\Resources\Sales;

use App\Http\Resources\Sales\Concerns\PresentsUsers;
use App\Models\Sales\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Lead */
class LeadResource extends JsonResource
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
            'name' => $this->name,
            'company_name' => $this->company_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'source' => $this->source?->value,
            'status' => $this->status?->value,
            'estimated_value' => $this->estimated_value,
            'assigned_to' => $this->assigned_to,
            'assignee' => $this->userSummary('assignee'),
            'notes' => $this->notes,
            'converted_customer_id' => $this->converted_customer_id,
            'converted_customer' => $this->whenLoaded('convertedCustomer', fn () => $this->convertedCustomer === null ? null : [
                'id' => $this->convertedCustomer->id,
                'name' => $this->convertedCustomer->name,
                'code' => $this->convertedCustomer->code,
            ]),
            'converted_at' => $this->converted_at?->toJSON(),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
