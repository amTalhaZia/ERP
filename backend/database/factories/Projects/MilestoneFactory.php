<?php

namespace Database\Factories\Projects;

use App\Enums\Projects\MilestoneStatus;
use App\Models\Projects\Milestone;
use App\Models\Projects\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Milestone>
 */
class MilestoneFactory extends Factory
{
    protected $model = Milestone::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'company_id' => fn (array $attributes) => Project::withoutTenancy()->findOrFail($attributes['project_id'])->company_id,
            'name' => ucfirst(fake()->words(2, true)),
            'due_date' => now()->addWeeks(2)->toDateString(),
            'status' => MilestoneStatus::Pending,
            'position' => 0,
        ];
    }

    public function completed(): static
    {
        return $this->state(['status' => MilestoneStatus::Completed, 'completed_at' => now()]);
    }
}
