<?php

namespace App\Http\Resources\HR;

use App\Models\HR\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LeaveType */
class LeaveTypeResource extends JsonResource
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
            'days_per_year' => $this->days_per_year,
            'is_paid' => $this->is_paid,
            'requires_approval' => $this->requires_approval,
            'carry_forward' => $this->carry_forward,
            'max_carry_forward_days' => $this->max_carry_forward_days,
            'color' => $this->color,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
