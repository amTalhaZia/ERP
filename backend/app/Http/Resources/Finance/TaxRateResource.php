<?php

namespace App\Http\Resources\Finance;

use App\Models\Finance\TaxRate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TaxRate */
class TaxRateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'code' => $this->code,
            'rate' => (string) $this->rate,
            'type' => $this->type->value,
            'account_id' => $this->account_id,
            'account' => $this->whenLoaded('account', fn () => $this->account ? ['id' => $this->account->id, 'code' => $this->account->code, 'name' => $this->account->name] : null),
            'is_compound' => $this->is_compound,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
