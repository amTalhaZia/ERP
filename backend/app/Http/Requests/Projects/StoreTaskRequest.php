<?php

namespace App\Http\Requests\Projects;

use App\Enums\Projects\Priority;
use App\Enums\Projects\TaskStatus;
use App\Http\Requests\Projects\Rules\CompanyMember;
use App\Models\Projects\Milestone;
use App\Models\Projects\Task;
use App\Support\Tenancy\Rules\TenantExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create a task (POST /projects/{project}/tasks), a subtask
 * (POST /tasks/{task}/subtasks) or update one (PATCH /tasks/{task}).
 */
class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:20000'],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'priority' => ['sometimes', Rule::enum(Priority::class)],
            'milestone_id' => ['nullable', 'integer', new TenantExists(Milestone::class, where: ['project_id' => $this->projectId()])],
            'assignee_id' => ['nullable', 'integer', new CompanyMember],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'estimate_hours' => ['nullable', 'numeric', 'min:0', 'max:999999.99', 'decimal:0,2'],
            'labels' => ['nullable', 'array', 'max:20'],
            'labels.*' => ['string', 'max:40', 'distinct'],
        ];
    }

    private function projectId(): int
    {
        $project = $this->route('project');

        if ($project !== null) {
            return (int) $project->id;
        }

        /** @var Task|null $task */
        $task = $this->route('task');

        return (int) $task?->project_id;
    }
}
