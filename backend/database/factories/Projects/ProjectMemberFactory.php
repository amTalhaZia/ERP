<?php

namespace Database\Factories\Projects;

use App\Enums\Projects\ProjectMemberRole;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectMember>
 */
class ProjectMemberFactory extends Factory
{
    protected $model = ProjectMember::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'company_id' => fn (array $attributes) => Project::withoutTenancy()->findOrFail($attributes['project_id'])->company_id,
            'user_id' => User::factory(),
            'role' => ProjectMemberRole::Member->value,
        ];
    }

    public function manager(): static
    {
        return $this->state(['role' => ProjectMemberRole::Manager->value]);
    }
}
