<?php

namespace Database\Factories\Payroll;

use App\Enums\Payroll\PayrollRunStatus;
use App\Models\HR\Employee;
use App\Models\Payroll\PayrollRun;
use App\Models\Payroll\Payslip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payslip>
 */
class PayslipFactory extends Factory
{
    protected $model = Payslip::class;

    public function definition(): array
    {
        return [
            'payroll_run_id' => PayrollRun::factory(),
            'company_id' => fn (array $attrs) => PayrollRun::withoutTenancy()->find($attrs['payroll_run_id'])->company_id,
            'employee_id' => fn (array $attrs) => Employee::factory()->create(['company_id' => $attrs['company_id']])->id,
            'status' => PayrollRunStatus::Draft,
            'employee_code' => 'EMP-'.fake()->numerify('#####'),
            'employee_name' => fake()->name(),
            'currency' => 'USD',
            'base_salary' => '5000.0000',
            'working_days' => '22.0',
            'paid_days' => '22.0',
            'earned_base' => '5000.0000',
            'gross' => '5000.0000',
            'taxable' => '5000.0000',
            'tax' => '0.0000',
            'total_deductions' => '0.0000',
            'employer_contributions' => '0.0000',
            'net' => '5000.0000',
        ];
    }
}
