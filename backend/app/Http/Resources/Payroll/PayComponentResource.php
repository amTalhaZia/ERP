<?php

namespace App\Http\Resources\Payroll;

use App\Models\Payroll\PayComponent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PayComponent */
class PayComponentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'kind' => $this->kind->value,
            'calculation' => $this->calculation->value,
            'default_amount' => (string) $this->default_amount,
            'default_rate' => (string) $this->default_rate,
            'is_taxable' => $this->is_taxable,
            'reduces_taxable' => $this->reduces_taxable,
            'affects_gross' => $this->affects_gross,
            'gl_account_code' => $this->gl_account_code,
            'effective_gl_account_code' => $this->accountCode(),
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
