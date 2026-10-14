<?php

namespace App\Http\Resources\Projects;

use App\Models\Projects\TimeEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TimeEntry */
class TimeEntryResource extends JsonResource
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
            'task_id' => $this->task_id,
            'task' => $this->whenLoaded('task', fn () => $this->task ? ['id' => $this->task->id, 'title' => $this->task->title] : null),
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn () => UserSummary::from($this->user)),
            'date' => $this->date?->toDateString(),
            'hours' => $this->hours,
            'description' => $this->description,
            'is_billable' => $this->is_billable,
            'hourly_rate' => $this->hourly_rate,
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
