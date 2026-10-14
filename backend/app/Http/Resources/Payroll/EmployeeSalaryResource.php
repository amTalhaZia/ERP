<?php

namespace App\Http\Resources\Payroll;

use App\Models\Payroll\EmployeeSalary;
use App\Models\Payroll\EmployeeSalaryComponent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmployeeSalary */
class EmployeeSalaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'salary_structure_id' => $this->salary_structure_id,
            'salary_structure' => $this->whenLoaded('structure', fn () => $this->structure ? ['id' => $this->structure->id, 'name' => $this->structure->name] : null),
            'base_salary' => (string) $this->base_salary,
            'currency' => $this->currency,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'pay_frequency' => $this->pay_frequency->value,
            'notes' => $this->notes,
            'components' => $this->whenLoaded('components', fn () => $this->components->map(fn (EmployeeSalaryComponent $c) => [
                'pay_component_id' => $c->pay_component_id,
                'code' => $c->component?->code,
                'name' => $c->component?->name,
                'amount' => $c->amount === null ? null : (string) $c->amount,
                'rate' => $c->rate === null ? null : (string) $c->rate,
            ])->values()),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator ? ['id' => $this->creator->id, 'name' => $this->creator->name] : null),
            'created_at' => $this->created_at?->toJSON(),
        ];
    }
}
