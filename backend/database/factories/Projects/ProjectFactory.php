<?php

namespace Database\Factories\Projects;

use App\Enums\Projects\Priority;
use App\Enums\Projects\ProjectStatus;
use App\Models\Company;
use App\Models\Projects\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'number' => 'PRJ-'.now()->format('Y').'-'.fake()->unique()->numerify('######'),
            'name' => ucfirst(fake()->words(3, true)),
            'code' => null,
            'description' => fake()->sentence(),
            'status' => ProjectStatus::Active,
            'priority' => Priority::Medium,
            'start_date' => now()->subWeek()->toDateString(),
            'due_date' => now()->addMonth()->toDateString(),
            'budget' => '10000.0000',
            'currency' => 'USD',
            'color' => '#3366FF',
            'is_billable' => true,
        ];
    }

    public function planning(): static
    {
        return $this->state(['status' => ProjectStatus::Planning]);
    }

    public function completed(): static
    {
        return $this->state(['status' => ProjectStatus::Completed, 'completed_at' => now()]);
    }

    public function managedBy(User $user): static
    {
        return $this->state(['manager_id' => $user->id]);
    }
}
