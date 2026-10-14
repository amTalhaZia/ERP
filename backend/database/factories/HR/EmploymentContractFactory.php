<?php

namespace Database\Factories\HR;

use App\Enums\HR\ContractStatus;
use App\Enums\HR\EmploymentType;
use App\Models\HR\Employee;
use App\Models\HR\EmploymentContract;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmploymentContract>
 */
class EmploymentContractFactory extends Factory
{
    protected $model = EmploymentContract::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'company_id' => fn (array $attrs) => Employee::withoutTenancy()->find($attrs['employee_id'])->company_id,
            'contract_type' => EmploymentType::FullTime,
            'start_date' => now()->subYear()->toDateString(),
            'end_date' => null,
            'salary' => '5000.0000',
            'currency' => 'USD',
            'working_hours_per_week' => '40.00',
            'status' => ContractStatus::Active,
        ];
    }
}
