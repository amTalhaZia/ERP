<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Finance', 'Human Resources', 'Sales', 'Operations', 'Engineering', 'Procurement',
            'Marketing', 'Customer Support', 'Logistics', 'Legal', 'IT', 'Quality Assurance',
        ]);

        return [
            'company_id' => Company::factory(),
            'name' => $name,
            'code' => Str::upper(Str::substr(Str::slug($name, ''), 0, 4)).fake()->numerify('##'),
            'is_active' => true,
        ];
    }
}
