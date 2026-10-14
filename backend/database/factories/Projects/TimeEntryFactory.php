<?php

namespace Database\Factories\Projects;

use App\Models\Projects\Project;
use App\Models\Projects\TimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    protected $model = TimeEntry::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'company_id' => fn (array $attributes) => Project::withoutTenancy()->findOrFail($attributes['project_id'])->company_id,
            'user_id' => User::factory(),
            'date' => now()->toDateString(),
            'hours' => '2.50',
            'description' => fake()->sentence(),
            'is_billable' => true,
            'hourly_rate' => '50.0000',
        ];
    }

    public function nonBillable(): static
    {
        return $this->state(['is_billable' => false, 'hourly_rate' => null]);
    }
}
