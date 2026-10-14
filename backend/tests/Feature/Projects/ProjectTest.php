<?php

namespace Tests\Feature\Projects;

use App\Enums\Projects\ProjectMemberRole;
use App\Models\Company;
use App\Models\Department;
use App\Models\Projects\Project;
use App\Models\User;
use Tests\Feature\Projects\Concerns\InteractsWithProjects;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use InteractsWithProjects;

    public function test_creating_a_project_numbers_it_adds_the_manager_as_member_and_audits(): void
    {
        [$user, $company] = $this->userWith($this->projectManagerPermissions);
        $manager = User::factory()->inCompany($company)->create();
        $department = Department::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAsIn($user, $company)->postJson('/api/v1/projects', [
            'name' => 'Website relaunch',
            'code' => 'WEB-1',
            'department_id' => $department->id,
            'manager_id' => $manager->id,
            'priority' => 'high',
            'start_date' => '2026-10-01',
            'due_date' => '2026-12-31',
            'budget' => '15000.50',
            'currency' => 'eur',
            'is_billable' => true,
        ]);

        $year = now()->format('Y');
        $response->assertCreated()
            ->assertJsonPath('data.number', "PRJ-{$year}-000001")
            ->assertJsonPath('data.status', 'planning')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.budget', '15000.5000')
            ->assertJsonPath('data.currency', 'EUR')
            ->assertJsonPath('data.manager.id', $manager->id)
            ->assertJsonPath('data.members.0.user_id', $manager->id)
            ->assertJsonPath('data.members.0.role', 'manager');

        $id = $response->json('data.id');
        $this->assertDatabaseHas('projects', ['id' => $id, 'company_id' => $company->id, 'created_by' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'projects.created', 'entity_type' => 'project', 'entity_id' => $id]);

        $this->postJson('/api/v1/projects', ['name' => 'Second'])
            ->assertCreated()
            ->assertJsonPath('data.number', "PRJ-{$year}-000002")
            ->assertJsonPath('data.manager_id', $user->id);
    }

    public function test_returns_422_for_empty_payload(): void
    {
        [$user, $company] = $this->userWith($this->projectManagerPermissions);

        $this->actingAsIn($user, $company)->postJson('/api/v1/projects', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_returns_422_when_referencing_another_companys_department_or_manager(): void
    {
        [$user, $company] = $this->userWith($this->projectManagerPermissions);
        $other = Company::factory()->create();
        $foreignDepartment = Department::factory()->create(['company_id' => $other->id]);
        $foreignUser = User::factory()->inCompany($other)->create();

        $this->actingAsIn($user, $company)->postJson('/api/v1/projects', [
            'name' => 'Leaky',
            'department_id' => $foreignDepartment->id,
            'manager_id' => $foreignUser->id,
            'due_date' => '2026-01-01',
            'start_date' => '2026-02-01',
            'status' => 'bogus',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['department_id', 'manager_id', 'due_date', 'status']);

        $this->assertDatabaseMissing('projects', ['name' => 'Leaky']);
    }

    public function test_returns_403_and_creates_nothing_without_manage_permission(): void
    {
        [$user, $company] = $this->userWith(['projects.view']);

        $this->actingAsIn($user, $company)->postJson('/api/v1/projects', ['name' => 'Nope'])
            ->assertForbidden()
            ->assertJsonPath('code', 'auth.forbidden');

        $this->assertDatabaseMissing('projects', ['name' => 'Nope']);
    }

    public function test_returns_403_when_listing_without_any_project_permission(): void
    {
        [$user, $company] = $this->userWith(['dashboard.view']);

        $this->actingAsIn($user, $company)->getJson('/api/v1/projects')->assertForbidden();
    }

    public function test_returns_404_for_another_companys_project(): void
    {
        [$user, $company] = $this->userWith($this->projectManagerPermissions);
        $foreign = $this->projectIn(Company::factory()->create(), ['name' => 'Foreign']);

        $this->actingAsIn($user, $company)->getJson("/api/v1/projects/{$foreign->id}")->assertNotFound();
        $this->patchJson("/api/v1/projects/{$foreign->id}", ['name' => 'Hacked'])->assertNotFound();
        $this->deleteJson("/api/v1/projects/{$foreign->id}")->assertNotFound();
        $this->getJson("/api/v1/projects/{$foreign->id}/summary")->assertNotFound();
        $this->getJson("/api/v1/projects/{$foreign->id}/board")->assertNotFound();

        $this->assertDatabaseHas('projects', ['id' => $foreign->id, 'name' => 'Foreign', 'deleted_at' => null]);
    }

    public function test_members_without_projects_view_only_see_their_own_projects(): void
    {
        [$user, $company] = $this->userWith($this->teamMemberPermissions);
        $mine = $this->projectIn($company, ['name' => 'Mine']);
        $notMine = $this->projectIn($company, ['name' => 'Not mine']);
        $this->addMember($mine, $user);

        $this->actingAsIn($user, $company)->getJson('/api/v1/projects')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id);

        $this->getJson("/api/v1/projects/{$mine->id}")->assertOk();
        $this->getJson("/api/v1/projects/{$notMine->id}")->assertForbidden();
        $this->getJson('/api/v1/projects/overview')
            ->assertOk()
            ->assertJsonPath('data.totals.projects', 1)
            ->assertJsonCount(1, 'data.projects');
    }

    public function test_updating_to_completed_stamps_completed_at_and_delete_is_soft(): void
    {
        [$user, $company] = $this->userWith($this->projectManagerPermissions);
        $project = $this->projectIn($company);

        $this->actingAsIn($user, $company)->patchJson("/api/v1/projects/{$project->id}", ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
        $this->assertNotNull(Project::withoutTenancy()->find($project->id)->completed_at);

        $this->deleteJson("/api/v1/projects/{$project->id}")->assertNoContent();
        $this->assertSoftDeleted('projects', ['id' => $project->id]);
    }

    public function test_members_can_be_added_updated_and_removed_but_not_the_manager(): void
    {
        [$user, $company] = $this->userWith($this->projectManagerPermissions);
        $colleague = User::factory()->inCompany($company)->create();
        $outsider = User::factory()->inCompany(Company::factory()->create())->create();
        $project = $this->projectIn($company, ['manager_id' => $user->id]);
        $managerMember = $this->addMember($project, $user, ProjectMemberRole::Manager);

        $this->actingAsIn($user, $company)->postJson("/api/v1/projects/{$project->id}/members", ['user_id' => $outsider->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);

        $memberId = $this->postJson("/api/v1/projects/{$project->id}/members", ['user_id' => $colleague->id, 'role' => 'viewer'])
            ->assertCreated()
            ->assertJsonPath('data.role', 'viewer')
            ->json('data.id');

        $this->postJson("/api/v1/projects/{$project->id}/members", ['user_id' => $colleague->id])
            ->assertUnprocessable();

        $this->patchJson("/api/v1/projects/{$project->id}/members/{$memberId}", ['role' => 'member'])
            ->assertOk()
            ->assertJsonPath('data.role', 'member');

        $this->deleteJson("/api/v1/projects/{$project->id}/members/{$managerMember->id}")->assertStatus(409);
        $this->deleteJson("/api/v1/projects/{$project->id}/members/{$memberId}")->assertNoContent();
        $this->assertDatabaseMissing('project_members', ['id' => $memberId]);
    }

    public function test_milestones_are_nested_for_create_and_shallow_for_update(): void
    {
        [$user, $company] = $this->userWith($this->projectManagerPermissions);
        $project = $this->projectIn($company);

        $this->actingAsIn($user, $company)->postJson("/api/v1/projects/{$project->id}/milestones", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $id = $this->postJson("/api/v1/projects/{$project->id}/milestones", ['name' => 'Beta', 'due_date' => '2026-11-30'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->json('data.id');

        $this->patchJson("/api/v1/milestones/{$id}", ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->getJson("/api/v1/projects/{$project->id}/milestones")->assertOk()->assertJsonCount(1, 'data');
    }
}
