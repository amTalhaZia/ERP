<?php

namespace Database\Factories\Projects;

use App\Enums\Projects\Priority;
use App\Enums\Projects\TaskStatus;
use App\Models\Projects\Project;
use App\Models\Projects\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'company_id' => fn (array $attributes) => Project::withoutTenancy()->findOrFail($attributes['project_id'])->company_id,
            'title' => ucfirst(fake()->words(4, true)),
            'description' => fake()->sentence(),
            'status' => TaskStatus::Todo,
            'priority' => Priority::Medium,
            'due_date' => now()->addWeek()->toDateString(),
            'estimate_hours' => '4.00',
            'position' => 0,
        ];
    }

    public function status(TaskStatus $status): static
    {
        return $this->state([
            'status' => $status,
            'completed_at' => $status === TaskStatus::Done ? now() : null,
        ]);
    }

    public function done(): static
    {
        return $this->status(TaskStatus::Done);
    }

    public function overdue(): static
    {
        return $this->state(['due_date' => now()->subDays(3)->toDateString()]);
    }

    public function subtaskOf(Task $parent): static
    {
        return $this->state(['project_id' => $parent->project_id, 'parent_id' => $parent->id]);
    }
}
