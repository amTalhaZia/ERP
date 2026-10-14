<?php

namespace App\Actions\Projects;

use App\Enums\Projects\Priority;
use App\Enums\Projects\ProjectMemberRole;
use App\Enums\Projects\TaskStatus;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectMember;
use App\Models\Projects\Task;
use App\Models\User;
use App\Notifications\Projects\TaskAssigned;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates/updates tasks and subtasks:
 *  - new tasks go to the end of their status column (siblings share project, parent and status);
 *  - a status change via update moves the task to the end of the new column;
 *  - `completed_at` follows the Done status;
 *  - a new assignee becomes a project member (if not already) and is notified.
 */
class SaveTaskAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Project $project, array $data, User $actor, ?Task $parent = null): Task
    {
        if ($parent !== null && $parent->depth() >= Task::MAX_DEPTH) {
            throw ValidationException::withMessages([
                'parent_id' => ['Subtasks can only be nested '.(Task::MAX_DEPTH - 1).' level deep.'],
            ]);
        }

        $task = DB::transaction(function () use ($project, $data, $actor, $parent): Task {
            $status = TaskStatus::from($this->enumValue($data['status'] ?? TaskStatus::Todo));

            $task = Task::query()->create(array_merge($data, [
                'project_id' => $project->id,
                'parent_id' => $parent?->id,
                'status' => $status,
                'priority' => $data['priority'] ?? Priority::Medium->value,
                'reporter_id' => $actor->id,
                'position' => $this->nextPosition($project->id, $parent?->id, $status),
                'completed_at' => $status === TaskStatus::Done ? now() : null,
            ]));

            if ($task->assignee_id !== null) {
                $this->ensureMember($project->id, $task->assignee_id);
            }

            return $task;
        });

        if ($task->assignee_id !== null) {
            $this->notifyAssignee($task, $project, $actor);
        }

        return $task;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Task $task, array $data, User $actor): Task
    {
        DB::transaction(function () use ($task, $data): void {
            $task->fill($data);

            if ($task->isDirty('status')) {
                $task->position = $this->nextPosition($task->project_id, $task->parent_id, $task->status, $task->id);
                $task->completed_at = $task->status === TaskStatus::Done ? now() : null;
            }

            $task->save();

            if ($task->wasChanged('assignee_id') && $task->assignee_id !== null) {
                $this->ensureMember($task->project_id, $task->assignee_id);
            }
        });

        if ($task->wasChanged('assignee_id') && $task->assignee_id !== null) {
            $this->notifyAssignee($task, $task->project()->firstOrFail(), $actor);
        }

        return $task;
    }

    private function nextPosition(int $projectId, ?int $parentId, TaskStatus $status, ?int $exceptId = null): int
    {
        $max = Task::query()
            ->where('project_id', $projectId)
            ->where('parent_id', $parentId)
            ->where('status', $status->value)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->max('position');

        return $max === null ? 0 : ((int) $max) + 1;
    }

    private function ensureMember(int $projectId, int $userId): void
    {
        ProjectMember::query()->firstOrCreate(
            ['project_id' => $projectId, 'user_id' => $userId],
            ['role' => ProjectMemberRole::Member->value],
        );
    }

    private function notifyAssignee(Task $task, Project $project, User $actor): void
    {
        if ($task->assignee_id === $actor->id) {
            return;
        }

        $assignee = User::query()->find($task->assignee_id);

        $assignee?->notify(new TaskAssigned($task, $project->name, $actor->name));
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof TaskStatus ? $value->value : (string) $value;
    }
}
