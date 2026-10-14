<?php

namespace Database\Factories\Projects;

use App\Models\Projects\Task;
use App\Models\Projects\TaskComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskComment>
 */
class TaskCommentFactory extends Factory
{
    protected $model = TaskComment::class;

    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'company_id' => fn (array $attributes) => Task::withoutTenancy()->findOrFail($attributes['task_id'])->company_id,
            'user_id' => User::factory(),
            'body' => fake()->paragraph(),
        ];
    }
}
