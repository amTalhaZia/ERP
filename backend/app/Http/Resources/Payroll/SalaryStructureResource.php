<?php

namespace App\Http\Resources\Payroll;

use App\Models\Payroll\SalaryStructure;
use App\Models\Payroll\SalaryStructureComponent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SalaryStructure */
class SalaryStructureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'currency' => $this->currency,
            'is_active' => $this->is_active,
            'components' => $this->whenLoaded('components', fn () => $this->components->map(fn (SalaryStructureComponent $c) => [
                'pay_component_id' => $c->pay_component_id,
                'code' => $c->component?->code,
                'name' => $c->component?->name,
                'kind' => $c->component?->kind->value,
                'calculation' => $c->component?->calculation->value,
                'amount' => $c->amount === null ? null : (string) $c->amount,
                'rate' => $c->rate === null ? null : (string) $c->rate,
            ])->values()),
            'components_count' => $this->whenCounted('components'),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
