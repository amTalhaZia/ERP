<?php

namespace Database\Factories\HR;

use App\Models\Company;
use App\Models\HR\Designation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Designation>
 */
class DesignationFactory extends Factory
{
    protected $model = Designation::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'title' => fake()->randomElement(['Software Engineer', 'Accountant', 'HR Officer', 'Sales Executive', 'Team Lead', 'Manager']),
            'code' => Str::upper(Str::random(6)),
            'level' => fake()->numberBetween(1, 8),
            'is_active' => true,
        ];
    }
}
