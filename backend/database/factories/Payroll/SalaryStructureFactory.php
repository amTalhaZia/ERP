<?php

namespace Database\Factories\Payroll;

use App\Models\Company;
use App\Models\Payroll\SalaryStructure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalaryStructure>
 */
class SalaryStructureFactory extends Factory
{
    protected $model = SalaryStructure::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'Staff grade '.fake()->unique()->numberBetween(1, 99999),
            'description' => 'Standard monthly package',
            'currency' => 'USD',
            'is_active' => true,
        ];
    }
}
