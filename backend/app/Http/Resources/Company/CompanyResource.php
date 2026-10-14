<?php

namespace App\Http\Resources\Company;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Company */
class CompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'legal_name' => $this->legal_name,
            'slug' => $this->slug,
            'code' => $this->code,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'tax_number' => $this->tax_number,
            'registration_number' => $this->registration_number,
            'address' => [
                'line1' => $this->address_line1,
                'line2' => $this->address_line2,
                'city' => $this->city,
                'state' => $this->state,
                'postal_code' => $this->postal_code,
                'country' => $this->country,
            ],
            'currency' => $this->currency,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'date_format' => $this->date_format,
            'fiscal_year_start_month' => $this->fiscal_year_start_month,
            'logo_url' => $this->logoUrl(),
            'primary_color' => $this->primary_color,
            'is_active' => $this->is_active,
            'is_demo' => $this->is_demo,
            'settings' => $this->settings ?? [],
            'branches_count' => $this->whenCounted('branches'),
            'users_count' => $this->whenCounted('memberships'),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
