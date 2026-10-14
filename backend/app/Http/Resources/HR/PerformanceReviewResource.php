<?php

namespace App\Http\Resources\HR;

use App\Models\HR\PerformanceReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PerformanceReview */
class PerformanceReviewResource extends JsonResource
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
            'reviewer_id' => $this->reviewer_id,
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->reviewer ? ['id' => $this->reviewer->id, 'name' => $this->reviewer->name] : null),
            'cycle' => $this->cycle,
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'status' => $this->status?->value,
            'overall_rating' => $this->overall_rating,
            'ratings' => $this->ratings ?? [],
            'strengths' => $this->strengths,
            'improvements' => $this->improvements,
            'goals' => $this->goals,
            'submitted_at' => $this->submitted_at?->toJSON(),
            'acknowledged_at' => $this->acknowledged_at?->toJSON(),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
