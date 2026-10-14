<?php

namespace App\Http\Resources\HR;

use App\Models\HR\EmployeeShift;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmployeeShift */
class EmployeeShiftResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'shift_id' => $this->shift_id,
            'shift' => $this->whenLoaded('shift', fn () => ['id' => $this->shift->id, 'name' => $this->shift->name]),
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'created_at' => $this->created_at?->toJSON(),
        ];
    }
}
