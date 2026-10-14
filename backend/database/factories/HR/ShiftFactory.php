<?php

namespace Database\Factories\HR;

use App\Models\Company;
use App\Models\HR\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'Standard Day',
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'break_minutes' => 60,
            'days_of_week' => [1, 2, 3, 4, 5],
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }
}
