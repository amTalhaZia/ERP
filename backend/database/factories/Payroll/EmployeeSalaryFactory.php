<?php

namespace Database\Factories\Payroll;

use App\Enums\Payroll\PayFrequency;
use App\Models\HR\Employee;
use App\Models\Payroll\EmployeeSalary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeSalary>
 */
class EmployeeSalaryFactory extends Factory
{
    protected $model = EmployeeSalary::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'company_id' => fn (array $attrs) => Employee::withoutTenancy()->find($attrs['employee_id'])->company_id,
            'salary_structure_id' => null,
            'base_salary' => '5000.0000',
            'currency' => 'USD',
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'pay_frequency' => PayFrequency::Monthly,
        ];
    }
}
