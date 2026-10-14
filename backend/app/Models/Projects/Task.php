<?php

namespace App\Models\Projects;

use App\Enums\Projects\Priority;
use App\Enums\Projects\TaskStatus;
use App\Models\User;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Projects\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id', 'project_id', 'parent_id', 'milestone_id', 'title', 'description', 'status', 'priority',
    'assignee_id', 'reporter_id', 'start_date', 'due_date', 'estimate_hours', 'position', 'completed_at', 'labels',
])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    /** Tasks nest at most this deep: task (1) → subtask (2). */
    public const MAX_DEPTH = 2;

    /** @var array<int, string> */
    protected array $auditExclude = ['position'];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => Priority::class,
            'start_date' => 'date',
            'due_date' => 'date',
            'estimate_hours' => 'decimal:2',
            'position' => 'integer',
            'completed_at' => 'datetime',
            'labels' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id')->orderBy('position')->orderBy('id');
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /** 1 for a top-level task, 2 for a subtask. */
    public function depth(): int
    {
        return $this->parent_id === null ? 1 : 2;
    }
}
