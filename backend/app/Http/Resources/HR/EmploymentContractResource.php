<?php

namespace App\Http\Resources\HR;

use App\Models\HR\EmploymentContract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmploymentContract */
class EmploymentContractResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'name' => $this->employee->fullName(),
                'employee_code' => $this->employee->employee_code,
            ]),
            'contract_type' => $this->contract_type?->value,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'salary' => $this->salary,
            'currency' => $this->currency,
            'working_hours_per_week' => $this->working_hours_per_week,
            'has_document' => $this->document_path !== null,
            'status' => $this->status?->value,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
