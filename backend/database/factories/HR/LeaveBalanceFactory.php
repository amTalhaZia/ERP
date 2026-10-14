<?php

namespace Database\Factories\HR;

use App\Models\HR\Employee;
use App\Models\HR\LeaveBalance;
use App\Models\HR\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveBalance>
 */
class LeaveBalanceFactory extends Factory
{
    protected $model = LeaveBalance::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'company_id' => fn (array $attrs) => Employee::withoutTenancy()->find($attrs['employee_id'])->company_id,
            'leave_type_id' => LeaveType::factory(),
            'year' => (int) now()->format('Y'),
            'entitled' => '20.0',
            'used' => '0.0',
            'carried_forward' => '0.0',
            'adjusted' => '0.0',
        ];
    }
}
