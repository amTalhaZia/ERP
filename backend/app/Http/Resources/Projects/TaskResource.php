<?php

namespace App\Http\Resources\Projects;

use App\Models\Projects\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Task */
class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'project' => $this->whenLoaded('project', fn () => $this->project ? ['id' => $this->project->id, 'number' => $this->project->number, 'name' => $this->project->name] : null),
            'parent_id' => $this->parent_id,
            'milestone_id' => $this->milestone_id,
            'milestone' => $this->whenLoaded('milestone', fn () => $this->milestone ? ['id' => $this->milestone->id, 'name' => $this->milestone->name] : null),
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status?->value,
            'priority' => $this->priority?->value,
            'assignee_id' => $this->assignee_id,
            'assignee' => $this->whenLoaded('assignee', fn () => UserSummary::from($this->assignee)),
            'reporter_id' => $this->reporter_id,
            'reporter' => $this->whenLoaded('reporter', fn () => UserSummary::from($this->reporter)),
            'start_date' => $this->start_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'estimate_hours' => $this->estimate_hours,
            'position' => $this->position,
            'completed_at' => $this->completed_at?->toJSON(),
            'labels' => $this->labels ?? [],
            'subtasks' => TaskResource::collection($this->whenLoaded('subtasks')),
            'subtasks_count' => $this->whenCounted('subtasks'),
            'comments_count' => $this->whenCounted('comments'),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
