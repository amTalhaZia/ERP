<?php

namespace Tests\Feature\Projects;

use App\Enums\Projects\TaskStatus;
use App\Models\Company;
use App\Models\Projects\Milestone;
use App\Models\Projects\ProjectExpense;
use App\Models\Projects\Task;
use App\Models\Projects\TimeEntry;
use App\Models\User;
use Tests\Feature\Projects\Concerns\InteractsWithProjects;
use Tests\TestCase;

class TimeAndSummaryTest extends TestCase
{
    use InteractsWithProjects;

    public function test_time_entry_hours_must_be_above_zero_and_at_most_24(): void
    {
        [$user, $company] = $this->userWith($this->teamMemberPermissions);
        $project = $this->projectIn($company);
        $this->addMember($project, $user);

        $this->actingAsIn($user, $company)->postJson('/api/v1/time-entries', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id', 'date', 'hours']);

        foreach (['0', '-1', '24.01', '25', '1.234'] as $hours) {
            $this->postJson('/api/v1/time-entries', ['project_id' => $project->id, 'date' => '2026-10-10', 'hours' => $hours])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['hours']);
        }

        $this->postJson('/api/v1/time-entries', ['project_id' => $project->id, 'date' => '2026-10-10', 'hours' => '24'])
            ->assertCreated()
            ->assertJsonPath('data.hours', '24.00')
            ->assertJsonPath('data.user_id', $user->id);

        $this->postJson('/api/v1/time-entries', ['project_id' => $project->id, 'date' => '2026-10-11', 'hours' => '0.25'])
            ->assertCreated();
    }

    public function test_cannot_log_time_on_invisible_or_foreign_projects_or_for_others(): void
    {
        [$user, $company] = $this->userWith($this->teamMemberPermissions);
        $notMine = $this->projectIn($company);
        $mine = $this->projectIn($company);
        $this->addMember($mine, $user);
        $colleague = User::factory()->inCompany($company)->create();
        $foreign = $this->projectIn(Company::factory()->create());
        $foreignTask = Task::factory()->create(['project_id' => $foreign->id]);

        $this->actingAsIn($user, $company)
            ->postJson('/api/v1/time-entries', ['project_id' => $notMine->id, 'date' => '2026-10-10', 'hours' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors(['project_id']);
        $this->postJson('/api/v1/time-entries', ['project_id' => $foreign->id, 'date' => '2026-10-10', 'hours' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors(['project_id']);
        $this->postJson('/api/v1/time-entries', ['project_id' => $mine->id, 'task_id' => $foreignTask->id, 'user_id' => $colleague->id, 'date' => '2026-10-10', 'hours' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors(['task_id', 'user_id']);

        $this->assertDatabaseCount('time_entries', 0);
    }

    public function test_users_edit_their_own_time_and_project_managers_edit_anyones(): void
    {
        [$user, $company] = $this->userWith($this->teamMemberPermissions);
        $manager = User::factory()->inCompany($company, $this->projectManagerPermissions)->create();
        $project = $this->projectIn($company);
        $this->addMember($project, $user);
        $own = TimeEntry::factory()->create(['project_id' => $project->id, 'user_id' => $user->id, 'hours' => '1.00']);
        $theirs = TimeEntry::factory()->create(['project_id' => $project->id, 'user_id' => $manager->id, 'hours' => '1.00']);
        $foreign = TimeEntry::factory()->create();

        $this->actingAsIn($user, $company)->patchJson("/api/v1/time-entries/{$own->id}", ['hours' => '3.5'])
            ->assertOk()->assertJsonPath('data.hours', '3.50');
        $this->patchJson("/api/v1/time-entries/{$theirs->id}", ['hours' => '9'])->assertForbidden();
        $this->deleteJson("/api/v1/time-entries/{$theirs->id}")->assertForbidden();
        $this->getJson("/api/v1/time-entries/{$foreign->id}")->assertNotFound();
        $this->getJson('/api/v1/time-entries')->assertOk()->assertJsonCount(1, 'data');

        $this->switchTo($manager, $company)
            ->patchJson("/api/v1/time-entries/{$own->id}", ['hours' => '2'])->assertOk();
        $this->getJson("/api/v1/time-entries?filter[project_id]={$project->id}&from=2000-01-01")
            ->assertOk()->assertJsonCount(2, 'data');

        $this->assertDatabaseHas('time_entries', ['id' => $theirs->id, 'hours' => '1.00']);
        $this->assertDatabaseHas('time_entries', ['id' => $own->id, 'hours' => '2.00']);
    }

    public function test_time_summary_groups_hours_by_user_task_and_billability(): void
    {
        [$user, $company] = $this->userWith($this->projectManagerPermissions);
        $other = User::factory()->inCompany($company)->create(['name' => 'Zed']);
        $project = $this->projectIn($company);
        $task = Task::factory()->create(['project_id' => $project->id, 'title' => 'Build']);
        TimeEntry::factory()->create(['project_id' => $project->id, 'task_id' => $task->id, 'user_id' => $user->id, 'hours' => '3.00', 'hourly_rate' => '100']);
        TimeEntry::factory()->nonBillable()->create(['project_id' => $project->id, 'user_id' => $user->id, 'hours' => '1.50']);
        TimeEntry::factory()->create(['project_id' => $project->id, 'task_id' => $task->id, 'user_id' => $other->id, 'hours' => '2.00', 'hourly_rate' => '50']);

        $response = $this->actingAsIn($user, $company)->getJson("/api/v1/projects/{$project->id}/time-summary")->assertOk();

        $response->assertJsonPath('data.totals.hours', '6.50')
            ->assertJsonPath('data.totals.billable_hours', '5.00')
            ->assertJsonPath('data.totals.non_billable_hours', '1.50')
            ->assertJsonPath('data.totals.billable_amount', '400.0000')
            ->assertJsonPath('data.by_task.0.title', 'Build')
            ->assertJsonPath('data.by_task.0.hours', '5.00')
            ->assertJsonPath('data.by_task.1.title', 'No task');

        $byUser = collect($response->json('data.by_user'))->keyBy('user_id');
        $this->assertSame('4.50', $byUser[$user->id]['hours']);
        $this->assertSame('3.00', $byUser[$user->id]['billable_hours']);
        $this->assertSame('2.00', $byUser[$other->id]['hours']);
    }

    public function test_summary_reports_progress_overdue_hours_budget_and_milestones(): void
    {
        [$user, $company] = $this->userWith($this->projectManagerPermissions);
        $project = $this->projectIn($company, ['budget' => '1000.0000']);
        $milestone = Milestone::factory()->create(['project_id' => $project->id]);
        Task::factory()->done()->create(['project_id' => $project->id, 'milestone_id' => $milestone->id, 'estimate_hours' => '4']);
        Task::factory()->create(['project_id' => $project->id, 'milestone_id' => $milestone->id, 'estimate_hours' => '4']);
        Task::factory()->status(TaskStatus::InProgress)->overdue()->create(['project_id' => $project->id, 'estimate_hours' => '2']);
        Task::factory()->status(TaskStatus::Blocked)->create(['project_id' => $project->id, 'estimate_hours' => null]);
        TimeEntry::factory()->create(['project_id' => $project->id, 'hours' => '5.00', 'hourly_rate' => '40', 'is_billable' => true]);
        TimeEntry::factory()->nonBillable()->create(['project_id' => $project->id, 'hours' => '1.00']);
        ProjectExpense::factory()->create(['project_id' => $project->id, 'amount' => '150.0000']);

        $this->actingAsIn($user, $company)->getJson("/api/v1/projects/{$project->id}/summary")
            ->assertOk()
            ->assertJsonPath('data.tasks.total', 4)
            ->assertJsonPath('data.tasks.by_status.done', 1)
            ->assertJsonPath('data.tasks.by_status.blocked', 1)
            ->assertJsonPath('data.tasks.percent_complete', 25)
            ->assertJsonPath('data.tasks.overdue_count', 1)
            ->assertJsonPath('data.hours.logged', '6.00')
            ->assertJsonPath('data.hours.estimated', '10.00')
            ->assertJsonPath('data.hours.percent_of_estimate', 60)
            ->assertJsonPath('data.budget.expenses', '150.0000')
            ->assertJsonPath('data.budget.billable_time_cost', '200.0000')
            ->assertJsonPath('data.budget.spent', '350.0000')
            ->assertJsonPath('data.budget.remaining', '650.0000')
            ->assertJsonPath('data.budget.percent_used', 35)
            ->assertJsonPath('data.milestones.total', 1)
            ->assertJsonPath('data.milestones.items.0.tasks_total', 2)
            ->assertJsonPath('data.milestones.items.0.percent_complete', 50);
    }

    public function test_project_expenses_require_manage_and_are_scoped_to_the_project(): void
    {
        [$user, $company] = $this->userWith($this->projectManagerPermissions);
        $viewer = User::factory()->inCompany($company, ['projects.view'])->create();
        $project = $this->projectIn($company, ['currency' => 'EUR']);
        $otherProject = $this->projectIn($company);
        $otherExpense = ProjectExpense::factory()->create(['project_id' => $otherProject->id]);

        $this->actingAsIn($user, $company)->postJson("/api/v1/projects/{$project->id}/expenses", [])
            ->assertUnprocessable()->assertJsonValidationErrors(['date', 'category', 'amount']);

        $this->postJson("/api/v1/projects/{$project->id}/expenses", ['date' => '2026-10-01', 'category' => 'travel', 'amount' => '99.9'])
            ->assertCreated()
            ->assertJsonPath('data.amount', '99.9000')
            ->assertJsonPath('data.currency', 'EUR');

        $this->getJson("/api/v1/projects/{$project->id}/expenses/{$otherExpense->id}")->assertNotFound();

        $this->switchTo($viewer, $company)
            ->getJson("/api/v1/projects/{$project->id}/expenses")->assertOk()->assertJsonCount(1, 'data');
        $this->postJson("/api/v1/projects/{$project->id}/expenses", ['date' => '2026-10-01', 'category' => 'x', 'amount' => '1'])
            ->assertForbidden();
    }
}
