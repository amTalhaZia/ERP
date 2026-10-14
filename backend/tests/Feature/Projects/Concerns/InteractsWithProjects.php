<?php

namespace Tests\Feature\Projects\Concerns;

use App\Enums\Projects\ProjectMemberRole;
use App\Models\Company;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectMember;
use App\Models\User;

/**
 * Shared arrangement helpers for Projects (and Documents) feature tests.
 *
 * Arrange cross-company fixtures BEFORE the first request: the scoped tenant
 * context survives between requests in the test process.
 */
trait InteractsWithProjects
{
    /** @var array<int, string> */
    protected array $projectManagerPermissions = [
        'projects.view', 'projects.manage', 'tasks.view', 'tasks.manage', 'time_entries.manage',
    ];

    /** @var array<int, string> */
    protected array $teamMemberPermissions = ['tasks.view', 'tasks.manage', 'time_entries.manage'];

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function projectIn(Company $company, array $attributes = []): Project
    {
        return Project::factory()->create(['company_id' => $company->id] + $attributes);
    }

    protected function addMember(Project $project, User $user, ProjectMemberRole $role = ProjectMemberRole::Member): ProjectMember
    {
        return ProjectMember::factory()->create([
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => $role->value,
        ]);
    }

    /**
     * Switch the acting user mid-test (Sanctum caches the first user).
     */
    protected function switchTo(User $user, Company $company): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAsIn($user, $company);
    }
}
