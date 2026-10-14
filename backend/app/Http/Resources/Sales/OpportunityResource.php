<?php

namespace App\Http\Resources\Sales;

use App\Http\Resources\Sales\Concerns\PresentsUsers;
use App\Models\Sales\Opportunity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Opportunity */
class OpportunityResource extends JsonResource
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
            'title' => $this->title,
            'customer_id' => $this->customer_id,
            'customer' => $this->whenLoaded('customer', fn () => $this->customer === null ? null : [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'code' => $this->customer->code,
            ]),
            'lead_id' => $this->lead_id,
            'stage_id' => $this->stage_id,
            'stage' => $this->whenLoaded('stage', fn () => [
                'id' => $this->stage->id,
                'name' => $this->stage->name,
                'color' => $this->stage->color,
                'probability' => $this->stage->probability,
            ]),
            'value' => $this->value,
            'currency' => $this->currency,
            'probability' => $this->probability,
            'expected_close_date' => $this->expected_close_date?->toDateString(),
            'status' => $this->status?->value,
            'lost_reason' => $this->lost_reason,
            'owner_id' => $this->owner_id,
            'owner' => $this->userSummary('owner'),
            'closed_at' => $this->closed_at?->toJSON(),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
