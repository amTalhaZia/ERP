<?php

namespace Database\Factories\Projects;

use App\Models\Projects\Project;
use App\Models\Projects\ProjectExpense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectExpense>
 */
class ProjectExpenseFactory extends Factory
{
    protected $model = ProjectExpense::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'company_id' => fn (array $attributes) => Project::withoutTenancy()->findOrFail($attributes['project_id'])->company_id,
            'date' => now()->toDateString(),
            'category' => fake()->randomElement(['travel', 'software', 'materials', 'subcontractor']),
            'description' => fake()->sentence(),
            'amount' => '250.0000',
            'currency' => 'USD',
        ];
    }
}
