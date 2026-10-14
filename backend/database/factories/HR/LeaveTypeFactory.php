<?php

namespace Database\Factories\HR;

use App\Models\Company;
use App\Models\HR\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    protected $model = LeaveType::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'Annual Leave',
            'code' => 'AL'.Str::upper(Str::random(4)),
            'days_per_year' => '20.0',
            'is_paid' => true,
            'requires_approval' => true,
            'carry_forward' => false,
            'max_carry_forward_days' => null,
            'color' => '#2563eb',
            'is_active' => true,
        ];
    }

    public function unpaid(): static
    {
        return $this->state(['name' => 'Unpaid Leave', 'days_per_year' => '0.0', 'is_paid' => false]);
    }
}
