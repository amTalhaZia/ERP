<?php

namespace Database\Factories\HR;

use App\Models\HR\Employee;
use App\Models\HR\EmployeeShift;
use App\Models\HR\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeShift>
 */
class EmployeeShiftFactory extends Factory
{
    protected $model = EmployeeShift::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'company_id' => fn (array $attrs) => Employee::withoutTenancy()->find($attrs['employee_id'])->company_id,
            'shift_id' => Shift::factory(),
            'effective_from' => now()->subYear()->toDateString(),
            'effective_to' => null,
        ];
    }
}
