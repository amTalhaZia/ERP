<?php

namespace App\Http\Resources\Finance;

use App\Models\Finance\Account;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Account */
class AccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'parent_id' => $this->parent_id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type->value,
            'subtype' => $this->subtype,
            'normal_balance' => $this->type->isDebitNormal() ? 'debit' : 'credit',
            'is_bank' => $this->is_bank,
            'is_cash' => $this->is_cash,
            'is_system' => $this->is_system,
            'is_active' => $this->is_active,
            'currency' => $this->currency,
            'description' => $this->description,
            'opening_balance' => (string) $this->opening_balance,
            'opening_balance_date' => $this->opening_balance_date?->toDateString(),
            'parent' => $this->whenLoaded('parent', fn () => ['id' => $this->parent->id, 'code' => $this->parent->code, 'name' => $this->parent->name]),
            'lines_count' => $this->whenCounted('lines'),
            'children_count' => $this->whenCounted('children'),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
