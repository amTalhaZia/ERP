<?php

namespace App\Http\Resources\Payroll;

use App\Models\Payroll\TaxBracket;
use App\Models\Payroll\TaxTable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TaxTable */
class TaxTableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'effective_from' => $this->effective_from?->toDateString(),
            'period' => $this->period->value,
            'is_active' => $this->is_active,
            'notes' => $this->notes,
            'brackets' => $this->whenLoaded('brackets', fn () => $this->brackets->map(fn (TaxBracket $b) => [
                'id' => $b->id,
                'from_amount' => (string) $b->from_amount,
                'to_amount' => $b->to_amount === null ? null : (string) $b->to_amount,
                'rate' => (string) $b->rate,
                'fixed_amount' => (string) $b->fixed_amount,
            ])->values()),
            'brackets_count' => $this->whenCounted('brackets'),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
