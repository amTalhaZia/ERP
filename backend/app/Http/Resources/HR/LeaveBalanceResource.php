<?php

namespace App\Http\Resources\HR;

use App\Models\HR\LeaveBalance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LeaveBalance */
class LeaveBalanceResource extends JsonResource
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
            'leave_type_id' => $this->leave_type_id,
            'leave_type' => $this->whenLoaded('leaveType', fn () => [
                'id' => $this->leaveType->id,
                'name' => $this->leaveType->name,
                'code' => $this->leaveType->code,
                'color' => $this->leaveType->color,
            ]),
            'year' => $this->year,
            'entitled' => $this->entitled,
            'used' => $this->used,
            'carried_forward' => $this->carried_forward,
            'adjusted' => $this->adjusted,
            'remaining' => $this->remaining(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
