<?php

namespace App\Http\Requests\Projects;

use App\Http\Requests\Projects\Rules\CompanyMember;
use App\Models\Projects\Project;
use App\Models\Projects\Task;
use App\Models\Projects\TimeEntry;
use App\Support\Tenancy\Rules\TenantExists;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreTimeEntryRequest extends FormRequest
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
        $creating = $this->isMethod('POST');
        $required = $creating ? 'required' : 'sometimes';
        /** @var TimeEntry|null $entry */
        $entry = $this->route('time_entry');
        $projectId = $this->input('project_id', $entry?->project_id);

        return [
            'project_id' => [
                $required, 'integer', new TenantExists(Project::class),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $visible = Project::query()->whereKey($value)->visibleTo($this->user())->exists();

                    if (! $visible) {
                        $fail('You cannot log time on this project.');
                    }
                },
            ],
            'task_id' => ['nullable', 'integer', new TenantExists(Task::class, where: ['project_id' => (int) $projectId])],
            'user_id' => [
                'sometimes', 'integer', new CompanyMember,
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ((int) $value !== $this->user()->id && ! $this->user()->hasPermission('projects.manage')) {
                        $fail('You may only log your own time.');
                    }
                },
            ],
            'date' => [$required, 'date', 'before_or_equal:'.now()->addYear()->toDateString()],
            'hours' => [$required, 'numeric', 'gt:0', 'lte:24', 'decimal:0,2'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_billable' => ['sometimes', 'boolean'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:99999999', 'decimal:0,4'],
        ];
    }
}
