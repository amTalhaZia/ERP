<?php

namespace App\Http\Resources\HR;

use App\Models\HR\AttendanceRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AttendanceRecord */
class AttendanceRecordResource extends JsonResource
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
                'department_id' => $this->employee->department_id,
            ]),
            'date' => $this->date?->toDateString(),
            'check_in' => $this->check_in?->toJSON(),
            'check_out' => $this->check_out?->toJSON(),
            'status' => $this->status?->value,
            'worked_minutes' => $this->worked_minutes,
            'late_minutes' => $this->late_minutes,
            'source' => $this->source?->value,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
