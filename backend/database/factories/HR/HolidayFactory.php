<?php

namespace Database\Factories\HR;

use App\Models\Company;
use App\Models\HR\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    protected $model = Holiday::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'branch_id' => null,
            'name' => fake()->randomElement(['New Year', 'Labour Day', 'Independence Day', 'Founders Day']),
            'date' => fake()->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
            'is_recurring' => false,
        ];
    }
}
