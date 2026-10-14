<?php

namespace Tests\Feature\Projects;

use App\Enums\Projects\TaskStatus;
use App\Models\Company;
use App\Models\Projects\Milestone;
use App\Models\Projects\Task;
use App\Models\Projects\TaskComment;
use App\Models\User;
use App\Notifications\Projects\TaskAssigned;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Projects\Concerns\InteractsWithProjects;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use InteractsWithProjects;

    public function test_member_creates_a_task_and_the_assignee_is_notified_and_added_to_the_project(): void
    {
        Notification::fake();
        [$user, $company] = $this->userWith($this->teamMemberPermissions);
        $assignee = User::factory()->inCompany($company)->create();
        $project = $this->projectIn($company);
        $this->addMember($project, $user);

        $response = $this->actingAsIn($user, $company)->postJson("/api/v1/projects/{$project->id}/tasks", [
            'title' => 'Draft the homepage copy',
            'assignee_id' => $assignee->id,
            'priority' => 'high',
            'estimate_hours' => '6.5',
            'labels' => ['copy', 'web'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'todo')
            ->assertJsonPath('data.position', 0)
            ->assertJsonPath('data.reporter_id', $user->id)
            ->assertJsonPath('data.assignee.id', $assignee->id)
            ->assertJsonPath('data.estimate_hours', '6.50')
            ->assertJsonPath('data.labels', ['copy', 'web']);

        $taskId = $response->json('data.id');
        $this->assertDatabaseHas('project_members', ['project_id' => $project->id, 'user_id' => $assignee->id, 'role' => 'member']);

        Notification::assertSentTo($assignee, TaskAssigned::class, function (TaskAssigned $notification, array $channels) use ($assignee, $taskId, $project): bool {
            $payload = $notification->toDatabase($assignee);

            return $channels === ['database']
                && $payload['type'] === 'task.assigned'
                && $payload['url'] === "/projects/{$project->id}?task={$taskId}";
        });
        Notification::assertNotSentTo($user, TaskAssigned::class);
    }

    public function test_reassigning_a_task_notifies_the_new_assignee_only(): void
    {
        Notification::fake();
        [$user, $company] = $this->userWith($this->projectManagerPermissions);
        $first = User::factory()->inCompany($company)->create();
        $second = User::factory()->inCompany($company)->create();
        $project = $this->projectIn($company);
        $task = Task::factory()->create(['project_id' => $project->id, 'assignee_id' => $first->id]);

        $this->actingAsIn($user, $company)->patchJson("/api/v1/tasks/{$task->id}", ['assignee_id' => $second->id])->assertOk();
        $this->patchJson("/api/v1/tasks/{$task->id}", ['title' => 'Renamed'])->assertOk();

        Notification::assertSentToTimes($second, TaskAssigned::class, 1);
        Notification::assertNotSentTo($first, TaskAssigned::class);
    }

    public function test_returns_422_for_empty_payload_and_foreign_assignee_or_milestone(): void
    {
        [$user, $company] = $this->userWith($this->projectManagerPermissions);
        $project = $this->projectIn($company);
        $otherProject = $this->projectIn($company);
        $otherMilestone = Milestone::factory()->create(['project_id' => $otherProject->id]);
        $foreignUser = User::factory()->inCompany(Company::factory()->create())->create();

        $this->actingAsIn($user, $company)->postJson("/api/v1/projects/{$project->id}/tasks", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);

        $this->postJson("/api/v1/projects/{$project->id}/tasks", [
            'title' => 'X',
            'assignee_id' => $foreignUser->id,
            'milestone_id' => $otherMilestone->id,
            'status' => 'cancelled',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assignee_id', 'milestone_id', 'status']);
    }

    public function test_non_member_with_tasks_manage_cannot_edit_or_create_tasks(): void
    {
        [$user, $company] = $this->userWith($this->teamMemberPermissions);
        $project = $this->projectIn($company);
        $task = Task::factory()->create(['project_id' => $project->id, 'title' => 'Original']);

        $this->actingAsIn($user, $company)->patchJson("/api/v1/tasks/{$task->id}", ['title' => 'Changed'])->assertForbidden();
        $this->patchJson("/api/v1/tasks/{$task->id}/move", ['status' => 'done', 'position' => 0])->assertForbidden();
        $this->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'New'])->assertForbidden();
        $this->deleteJson("/api/v1/tasks/{$task->id}")->assertForbidden();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Original', 'status' => 'todo']);
        $this->assertDatabaseMissing('tasks', ['title' => 'New']);
    }

    public function test_viewer_without_tasks_manage_cannot_edit_even_as_member(): void
    {
        [$user, $company] = $this->userWith(['tasks.view']);
        $project = $this->projectIn($company);
        $this->addMember($project, $user);
        $task = Task::factory()->create(['project_id' => $project->id]);

        $this->actingAsIn($user, $company)->getJson("/api/v1/tasks/{$task->id}")->assertOk();
        $this->patchJson("/api/v1/tasks/{$task->id}", ['title' => 'Changed'])->assertForbidden();
    }

    public function test_returns_404_for_another_companys_task(): void
    {
        [$user, $company] = $this->userWith($this->projectManagerPermissions);
        $foreignTask = Task::factory()->create(['title' => 'Foreign']);

        $this->actingAsIn($user, $company)->getJson("/api/v1/tasks/{$foreignTask->id}")->assertNotFound();
        $this->patchJson("/api/v1/tasks/{$foreignTask->id}", ['title' => 'Hacked'])->assertNotFound();
        $this->patchJson("/api/v1/tasks/{$foreignTask->id}/move", ['status' => 'done', 'position' => 0])->assertNotFound();
        $this->postJson("/api/v1/tasks/{$foreignTask->id}/subtasks", ['title' => 'Sub'])->assertNotFound();
        $this->getJson("/api/v1/tasks/{$foreignTask->id}/comments")->assertNotFound();
        $this->deleteJson("/api/v1/tasks/{$foreignTask->id}")->assertNotFound();

        $this->assertDatabaseHas('tasks', ['id' => $foreignTask->id, 'title' => 'Foreign']);
    }

    public function test_subtasks_are_limited_to_one_level(): void
    {
        [$user, $company] = $this->userWith($this->projectManagerPermissions);
        $project = $this->projectIn($company);
        $task = Task::factory()->create(['project_id' => $project->id]);

        $subtaskId = $this->actingAsIn($user, $company)->postJson("/api/v1/tasks/{$task->id}/subtasks", ['title' => 'Child'])
            ->assertCreated()
            ->assertJsonPath('data.parent_id', $task->id)
            ->json('data.id');

        $this->postJson("/api/v1/tasks/{$subtaskId}/subtasks", ['title' => 'Grandchild'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id']);

        $this->assertDatabaseMissing('tasks', ['title' => 'Grandchild']);
        $this->getJson("/api/v1/tasks/{$task->id}")->assertOk()->assertJsonPath('data.subtasks_count', 1);
    }

    public function test_task_list_filters_and_my_tasks(): void
    {
        [$user, $company] = $this->userWith($this->projectManagerPermissions);
        $project = $this->projectIn($company);
        $mine = Task::factory()->overdue()->create(['project_id' => $project->id, 'assignee_id' => $user->id]);
        Task::factory()->done()->create(['project_id' => $project->id, 'assignee_id' => $user->id]);
        Task::factory()->create(['project_id' => $project->id]);

        $this->actingAsIn($user, $company)->getJson("/api/v1/projects/{$project->id}/tasks?filter[assignee_id]={$user->id}")
            ->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/v1/projects/{$project->id}/tasks?due=overdue")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
        $this->getJson('/api/v1/my-tasks')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.project.id', $project->id);
        $this->getJson('/api/v1/my-tasks?include_done=1')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_comments_are_edited_by_the_author_and_deleted_by_author_or_project_manager(): void
    {
        [$author, $company] = $this->userWith($this->teamMemberPermissions);
        $colleague = User::factory()->inCompany($company, $this->teamMemberPermissions)->create();
        $manager = User::factory()->inCompany($company, $this->projectManagerPermissions)->create();
        $project = $this->projectIn($company);
        $this->addMember($project, $author);
        $this->addMember($project, $colleague);
        $task = Task::factory()->create(['project_id' => $project->id]);
        $foreignComment = TaskComment::factory()->create();

        $this->actingAsIn($author, $company)->postJson("/api/v1/tasks/{$task->id}/comments", [])
            ->assertUnprocessable()->assertJsonValidationErrors(['body']);
        $this->postJson("/api/v1/tasks/{$task->id}/comments", ['body' => str_repeat('a', 5001)])
            ->assertUnprocessable()->assertJsonValidationErrors(['body']);

        $commentId = $this->postJson("/api/v1/tasks/{$task->id}/comments", ['body' => 'Looks good'])
            ->assertCreated()->assertJsonPath('data.user.id', $author->id)->json('data.id');
        $secondId = $this->postJson("/api/v1/tasks/{$task->id}/comments", ['body' => 'Second'])->assertCreated()->json('data.id');

        $this->patchJson("/api/v1/tasks/{$task->id}/comments/{$commentId}", ['body' => 'Edited'])
            ->assertOk()->assertJsonPath('data.body', 'Edited');
        $this->assertNotNull(TaskComment::query()->find($commentId)->edited_at);
        $this->patchJson("/api/v1/tasks/{$task->id}/comments/{$foreignComment->id}", ['body' => 'x'])->assertNotFound();

        $this->switchTo($colleague, $company)
            ->patchJson("/api/v1/tasks/{$task->id}/comments/{$commentId}", ['body' => 'Hijack'])->assertForbidden();
        $this->deleteJson("/api/v1/tasks/{$task->id}/comments/{$commentId}")->assertForbidden();

        $this->switchTo($manager, $company)
            ->deleteJson("/api/v1/tasks/{$task->id}/comments/{$commentId}")->assertNoContent();

        $this->switchTo($author, $company)
            ->deleteJson("/api/v1/tasks/{$task->id}/comments/{$secondId}")->assertNoContent();

        $this->assertDatabaseCount('task_comments', 1);
        $this->assertSame(TaskStatus::Todo, $task->refresh()->status);
    }
}
