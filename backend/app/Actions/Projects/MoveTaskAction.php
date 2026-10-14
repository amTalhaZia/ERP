<?php

namespace App\Actions\Projects;

use App\Enums\Projects\TaskStatus;
use App\Models\Projects\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Kanban move: put the task at index `position` (0-based, clamped) of the
 * `status` column and renumber both affected columns 0..n-1 so positions are
 * always dense. Siblings = same project, same parent (top-level tasks or the
 * subtasks of one parent), same status. Rows are locked for the duration.
 */
class MoveTaskAction
{
    public function handle(Task $task, TaskStatus $status, int $position): Task
    {
        return DB::transaction(function () use ($task, $status, $position): Task {
            $siblings = Task::query()
                ->where('project_id', $task->project_id)
                ->where('parent_id', $task->parent_id)
                ->whereIn('status', array_unique([$task->status->value, $status->value]))
                ->orderBy('position')
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'status', 'position']);

            $fromStatus = $task->status;

            $target = $siblings
                ->filter(fn (Task $t) => $t->status === $status && $t->id !== $task->id)
                ->values();

            $position = max(0, min($position, $target->count()));
            $target->splice($position, 0, [$task]);
            $this->renumber($target);

            if ($fromStatus !== $status) {
                $this->renumber($siblings->filter(fn (Task $t) => $t->status === $fromStatus && $t->id !== $task->id)->values());

                $task->status = $status;
                $task->completed_at = $status === TaskStatus::Done ? now() : null;
            }

            $task->position = $position;
            $task->save();

            return $task->refresh();
        });
    }

    /**
     * @param  Collection<int, Task>  $column
     */
    private function renumber(Collection $column): void
    {
        foreach ($column as $index => $sibling) {
            if ($sibling->position !== $index) {
                Task::query()->whereKey($sibling->id)->update(['position' => $index]);
            }
        }
    }
}
