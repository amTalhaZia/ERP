<?php

namespace Database\Factories\HR;

use App\Enums\HR\ReviewStatus;
use App\Models\HR\Employee;
use App\Models\HR\PerformanceReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceReview>
 */
class PerformanceReviewFactory extends Factory
{
    protected $model = PerformanceReview::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'company_id' => fn (array $attrs) => Employee::withoutTenancy()->find($attrs['employee_id'])->company_id,
            'reviewer_id' => null,
            'cycle' => '2026-H1',
            'period_start' => '2026-01-01',
            'period_end' => '2026-06-30',
            'status' => ReviewStatus::Draft,
            'overall_rating' => '4.0',
            'ratings' => [['criterion' => 'Delivery', 'rating' => 4, 'comment' => 'Solid']],
            'strengths' => fake()->sentence(),
        ];
    }

    public function submitted(): static
    {
        return $this->state(['status' => ReviewStatus::Submitted, 'submitted_at' => now()]);
    }
}
