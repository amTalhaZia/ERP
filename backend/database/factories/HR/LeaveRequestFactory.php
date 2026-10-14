<?php

namespace Database\Factories\HR;

use App\Enums\HR\LeaveRequestStatus;
use App\Models\HR\Employee;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    protected $model = LeaveRequest::class;

    public function definition(): array
    {
        $start = now()->addWeek()->startOfWeek();

        return [
            'employee_id' => Employee::factory(),
            'company_id' => fn (array $attrs) => Employee::withoutTenancy()->find($attrs['employee_id'])->company_id,
            'leave_type_id' => LeaveType::factory(),
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDays(2)->toDateString(),
            'days' => '3.0',
            'reason' => fake()->sentence(),
            'status' => LeaveRequestStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(['status' => LeaveRequestStatus::Approved, 'approved_at' => now()]);
    }
}
