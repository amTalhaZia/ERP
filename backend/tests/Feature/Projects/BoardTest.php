<?php

namespace Tests\Feature\Projects;

use App\Enums\Projects\TaskStatus;
use App\Models\Projects\Project;
use App\Models\Projects\Task;
use Tests\Feature\Projects\Concerns\InteractsWithProjects;
use Tests\TestCase;

class BoardTest extends TestCase
{
    use InteractsWithProjects;

    /**
     * @return array<string, int> title => position
     */
    private function column(Project $project, TaskStatus $status): array
    {
        return Task::withoutTenancy()
            ->where('project_id', $project->id)
            ->whereNull('parent_id')
            ->where('status', $status->value)
            ->orderBy('position')
            ->pluck('position', 'title')
            ->map(fn ($p) => (int) $p)
            ->all();
    }

    public function test_board_returns_one_column_per_status_with_ordered_tasks(): void
    {
        [$user, $company] = $this->userWith($this->projectManagerPermissions);
        $project = $this->projectIn($company);
        $a = Task::factory()->create(['project_id' => $project->id, 'title' => 'A', 'position' => 1, 'assignee_id' => $user->id]);
        Task::factory()->create(['project_id' => $project->id, 'title' => 'B', 'position' => 0]);
        Task::factory()->status(TaskStatus::Done)->create(['project_id' => $project->id, 'title' => 'C']);
        Task::factory()->subtaskOf($a)->create(['title' => 'A.1']);

        $this->actingAsIn($user, $company)->getJson("/api/v1/projects/{$project->id}/board")
            ->assertOk()
            ->assertJsonCount(5, 'data.columns')
            ->assertJsonPath('data.columns.0.status', 'todo')
            ->assertJsonPath('data.columns.0.tasks.0.title', 'B')
            ->assertJsonPath('data.columns.0.tasks.1.title', 'A')
            ->assertJsonPath('data.columns.0.tasks.1.subtasks_count', 1)
            ->assertJsonPath('data.columns.0.tasks.1.comments_count', 0)
            ->assertJsonPath('data.columns.0.tasks.1.assignee.id', $user->id)
            ->assertJsonPath('data.columns.3.status', 'done')
            ->assertJsonCount(1, 'data.columns.3.tasks');
    }

    public function test_moving_within_a_column_shifts_siblings(): void
    {
        [$user, $company] = $this->userWith($this->projectManagerPermissions);
        $project = $this->projectIn($company);
        Task::factory()->create(['project_id' => $project->id, 'title' => 'A', 'position' => 0]);
        Task::factory()->create(['project_id' => $project->id, 'title' => 'B', 'position' => 1]);
        $c = Task::factory()->create(['project_id' => $project->id, 'title' => 'C', 'position' => 2]);

        $this->actingAsIn($user, $company)->patchJson("/api/v1/tasks/{$c->id}/move", ['status' => 'todo', 'position' => 0])
            ->assertOk()
            ->assertJsonPath('data.position', 0);

        $this->assertSame(['C' => 0, 'A' => 1, 'B' => 2], $this->column($project, TaskStatus::Todo));
    }

    public function test_moving_across_columns_renumbers_both_and_sets_completed_at(): void
    {
        [$user, $company] = $this->userWith($this->projectManagerPermissions);
        $project = $this->projectIn($company);
        $a = Task::factory()->create(['project_id' => $project->id, 'title' => 'A', 'position' => 0]);
        Task::factory()->create(['project_id' => $project->id, 'title' => 'B', 'position' => 1]);
        Task::factory()->create(['project_id' => $project->id, 'title' => 'C', 'position' => 2]);
        Task::factory()->status(TaskStatus::Done)->create(['project_id' => $project->id, 'title' => 'X', 'position' => 0]);
        Task::factory()->status(TaskStatus::Done)->create(['project_id' => $project->id, 'title' => 'Y', 'position' => 1]);

        $this->actingAsIn($user, $company)->patchJson("/api/v1/tasks/{$a->id}/move", ['status' => 'done', 'position' => 1])
            ->assertOk()
            ->assertJsonPath('data.status', 'done');

        $this->assertSame(['B' => 0, 'C' => 1], $this->column($project, TaskStatus::Todo));
        $this->assertSame(['X' => 0, 'A' => 1, 'Y' => 2], $this->column($project, TaskStatus::Done));
        $this->assertNotNull($a->refresh()->completed_at);

        // Out-of-range positions are clamped to the end of the column.
        $this->patchJson("/api/v1/tasks/{$a->id}/move", ['status' => 'in_progress', 'position' => 99])
            ->assertOk()->assertJsonPath('data.position', 0);
        $this->assertNull($a->refresh()->completed_at);
        $this->assertSame(['X' => 0, 'Y' => 1], $this->column($project, TaskStatus::Done));
    }

    public function test_move_requires_status_and_position(): void
    {
        [$user, $company] = $this->userWith($this->projectManagerPermissions);
        $task = Task::factory()->create(['project_id' => $this->projectIn($company)->id]);

        $this->actingAsIn($user, $company)->patchJson("/api/v1/tasks/{$task->id}/move", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'position']);

        $this->patchJson("/api/v1/tasks/{$task->id}/move", ['status' => 'archived', 'position' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'position']);
    }
}
