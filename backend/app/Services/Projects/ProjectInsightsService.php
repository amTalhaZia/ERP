<?php

namespace App\Services\Projects;

use App\Enums\Projects\MilestoneStatus;
use App\Enums\Projects\ProjectStatus;
use App\Enums\Projects\TaskStatus;
use App\Http\Resources\Projects\UserSummary;
use App\Models\Projects\Milestone;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectExpense;
use App\Models\Projects\Task;
use App\Models\Projects\TimeEntry;
use App\Models\User;
use App\Support\Money\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read models for the project screens: Kanban board, progress summary,
 * time summary and the portfolio overview. All money/hours are strings.
 *
 * Note: expenses and time cost are summed as-is in the project currency;
 * multi-currency conversion is not performed.
 */
class ProjectInsightsService
{
    /**
     * Kanban board: one column per TaskStatus (board order) with top-level
     * tasks ordered by position.
     *
     * @param  array{assignee_id?: int|null, milestone_id?: int|null}  $filters
     * @return array<int, array<string, mixed>>
     */
    public function board(Project $project, array $filters = []): array
    {
        $tasks = Task::query()
            ->where('project_id', $project->id)
            ->whereNull('parent_id')
            ->when($filters['assignee_id'] ?? null, fn (Builder $q, $id) => $q->where('assignee_id', $id))
            ->when($filters['milestone_id'] ?? null, fn (Builder $q, $id) => $q->where('milestone_id', $id))
            ->with('assignee:'.UserSummary::COLUMNS)
            ->withCount(['subtasks', 'comments'])
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id', 'project_id', 'milestone_id', 'title', 'status', 'priority', 'assignee_id', 'due_date', 'position', 'labels', 'estimate_hours']);

        $grouped = $tasks->groupBy(fn (Task $task) => $task->status->value);

        return array_map(fn (TaskStatus $status) => [
            'status' => $status->value,
            'label' => $status->label(),
            'tasks' => $grouped->get($status->value, collect())->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status->value,
                'priority' => $task->priority?->value,
                'position' => $task->position,
                'milestone_id' => $task->milestone_id,
                'due_date' => $task->due_date?->toDateString(),
                'is_overdue' => $task->due_date !== null && $task->status !== TaskStatus::Done && $task->due_date->lt(today()),
                'labels' => $task->labels ?? [],
                'estimate_hours' => $task->estimate_hours,
                'assignee' => UserSummary::from($task->assignee),
                'subtasks_count' => (int) $task->subtasks_count,
                'comments_count' => (int) $task->comments_count,
            ])->values()->all(),
        ], TaskStatus::cases());
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Project $project): array
    {
        $byStatus = Task::query()
            ->where('project_id', $project->id)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $counts = [];
        foreach (TaskStatus::cases() as $status) {
            $counts[$status->value] = (int) ($byStatus[$status->value] ?? 0);
        }

        // Tasks have no "cancelled" state today; the denominator is every task.
        $total = array_sum($counts);
        $done = $counts[TaskStatus::Done->value];

        $overdue = Task::query()
            ->where('project_id', $project->id)
            ->where('status', '!=', TaskStatus::Done->value)
            ->whereDate('due_date', '<', today())
            ->with('assignee:'.UserSummary::COLUMNS)
            ->orderBy('due_date')
            ->limit(10)
            ->get(['id', 'title', 'status', 'due_date', 'assignee_id']);

        $overdueCount = Task::query()
            ->where('project_id', $project->id)
            ->where('status', '!=', TaskStatus::Done->value)
            ->whereDate('due_date', '<', today())
            ->count();

        $hoursLogged = $this->decimal(TimeEntry::query()->where('project_id', $project->id)->sum('hours'), 2);
        $estimate = $this->decimal(Task::query()->where('project_id', $project->id)->sum('estimate_hours'), 2);

        $expenses = Money::normalize((string) ProjectExpense::query()->where('project_id', $project->id)->sum('amount'));
        $timeCost = Money::normalize((string) (TimeEntry::query()
            ->where('project_id', $project->id)
            ->where('is_billable', true)
            ->whereNotNull('hourly_rate')
            ->selectRaw('COALESCE(SUM(hours * hourly_rate), 0) as cost')
            ->value('cost') ?? '0'));
        $spent = Money::add($expenses, $timeCost);
        $budget = $project->budget !== null ? Money::normalize((string) $project->budget) : null;

        $milestones = Milestone::query()
            ->where('project_id', $project->id)
            ->withCount([
                'tasks',
                'tasks as done_tasks_count' => fn (Builder $q) => $q->where('status', TaskStatus::Done->value),
            ])
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return [
            'project' => ['id' => $project->id, 'number' => $project->number, 'name' => $project->name, 'status' => $project->status->value],
            'tasks' => [
                'total' => $total,
                'by_status' => $counts,
                'percent_complete' => $this->percent($done, $total),
                'overdue_count' => $overdueCount,
                'overdue' => $overdue->map(fn (Task $task) => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'status' => $task->status->value,
                    'due_date' => $task->due_date?->toDateString(),
                    'assignee' => UserSummary::from($task->assignee),
                ])->all(),
            ],
            'hours' => [
                'logged' => $hoursLogged,
                'estimated' => $estimate,
                'remaining' => bcsub($estimate, $hoursLogged, 2),
                'percent_of_estimate' => bccomp($estimate, '0', 2) === 1
                    ? round((float) bcdiv(bcmul($hoursLogged, '100', 4), $estimate, 4), 1)
                    : null,
            ],
            'budget' => [
                'currency' => $project->currency,
                'budget' => $budget,
                'expenses' => $expenses,
                'billable_time_cost' => $timeCost,
                'spent' => $spent,
                'remaining' => $budget !== null ? Money::sub($budget, $spent) : null,
                'percent_used' => $budget !== null && ! Money::isZero($budget)
                    ? round((float) Money::div(Money::mul($spent, '100'), $budget), 1)
                    : null,
            ],
            'milestones' => [
                'total' => $milestones->count(),
                'completed' => $milestones->where('status', MilestoneStatus::Completed)->count(),
                'items' => $milestones->map(fn (Milestone $m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'status' => $m->status->value,
                    'due_date' => $m->due_date?->toDateString(),
                    'tasks_total' => (int) $m->tasks_count,
                    'tasks_done' => (int) $m->done_tasks_count,
                    'percent_complete' => $this->percent((int) $m->done_tasks_count, (int) $m->tasks_count),
                ])->all(),
            ],
        ];
    }

    /**
     * Hours by user, by task, and billable vs non-billable.
     *
     * @return array<string, mixed>
     */
    public function timeSummary(Project $project, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $base = fn () => TimeEntry::query()
            ->where('time_entries.project_id', $project->id)
            ->when($from, fn (Builder $q) => $q->whereDate('time_entries.date', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('time_entries.date', '<=', $to));

        $byUser = $base()
            ->join('users', 'users.id', '=', 'time_entries.user_id')
            ->groupBy('time_entries.user_id', 'users.name')
            ->orderBy('users.name')
            ->selectRaw('time_entries.user_id, users.name, SUM(time_entries.hours) as hours, SUM(CASE WHEN time_entries.is_billable = 1 THEN time_entries.hours ELSE 0 END) as billable_hours')
            ->toBase()
            ->get()
            ->map(fn (object $row) => [
                'user_id' => (int) $row->user_id,
                'name' => $row->name,
                'hours' => $this->decimal($row->hours, 2),
                'billable_hours' => $this->decimal($row->billable_hours, 2),
            ])->all();

        $byTask = $base()
            ->leftJoin('tasks', 'tasks.id', '=', 'time_entries.task_id')
            ->groupBy('time_entries.task_id', 'tasks.title')
            ->orderByDesc('hours')
            ->selectRaw('time_entries.task_id, tasks.title, SUM(time_entries.hours) as hours')
            ->toBase()
            ->get()
            ->map(fn (object $row) => [
                'task_id' => $row->task_id !== null ? (int) $row->task_id : null,
                'title' => $row->title ?? 'No task',
                'hours' => $this->decimal($row->hours, 2),
            ])->all();

        $totals = $base()
            ->selectRaw('COALESCE(SUM(hours), 0) as hours')
            ->selectRaw('COALESCE(SUM(CASE WHEN is_billable = 1 THEN hours ELSE 0 END), 0) as billable_hours')
            ->selectRaw('COALESCE(SUM(CASE WHEN is_billable = 1 THEN hours * COALESCE(hourly_rate, 0) ELSE 0 END), 0) as billable_amount')
            ->toBase()
            ->first();

        $hours = $this->decimal($totals->hours ?? 0, 2);
        $billable = $this->decimal($totals->billable_hours ?? 0, 2);

        return [
            'project_id' => $project->id,
            'from' => $from?->toDateString(),
            'to' => $to?->toDateString(),
            'totals' => [
                'hours' => $hours,
                'billable_hours' => $billable,
                'non_billable_hours' => bcsub($hours, $billable, 2),
                'billable_amount' => Money::normalize((string) ($totals->billable_amount ?? '0')),
                'currency' => $project->currency,
            ],
            'by_user' => $byUser,
            'by_task' => $byTask,
        ];
    }

    /**
     * Portfolio stats for the projects index page (projects visible to $user).
     *
     * @return array<string, mixed>
     */
    public function overview(User $user, ?string $status = null): array
    {
        $visible = fn () => Project::query()->visibleTo($user);

        $byStatus = $visible()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $counts = [];
        foreach (ProjectStatus::cases() as $case) {
            $counts[$case->value] = (int) ($byStatus[$case->value] ?? 0);
        }

        $openStatuses = [ProjectStatus::Planning->value, ProjectStatus::Active->value, ProjectStatus::OnHold->value];

        $projects = $visible()
            ->when($status, fn (Builder $q, string $s) => $q->where('status', $s))
            ->with('manager:'.UserSummary::COLUMNS)
            ->withCount([
                'tasks',
                'tasks as done_tasks_count' => fn (Builder $q) => $q->where('status', TaskStatus::Done->value),
                'tasks as overdue_tasks_count' => fn (Builder $q) => $q->where('status', '!=', TaskStatus::Done->value)->whereDate('due_date', '<', today()),
            ])
            ->withSum('timeEntries as hours_logged', 'hours')
            ->orderByRaw('due_date IS NULL, due_date')
            ->orderBy('id')
            ->limit(200)
            ->get();

        return [
            'totals' => [
                'projects' => array_sum($counts),
                'by_status' => $counts,
                'open' => $counts['planning'] + $counts['active'] + $counts['on_hold'],
                'overdue_projects' => $visible()->whereIn('status', $openStatuses)->whereDate('due_date', '<', today())->count(),
                'overdue_tasks' => Task::query()
                    ->whereIn('project_id', $visible()->select('projects.id'))
                    ->where('status', '!=', TaskStatus::Done->value)
                    ->whereDate('due_date', '<', today())
                    ->count(),
                'my_open_tasks' => Task::query()
                    ->where('assignee_id', $user->id)
                    ->where('status', '!=', TaskStatus::Done->value)
                    ->count(),
            ],
            'projects' => $projects->map(fn (Project $project) => [
                'id' => $project->id,
                'number' => $project->number,
                'name' => $project->name,
                'status' => $project->status->value,
                'priority' => $project->priority?->value,
                'color' => $project->color,
                'due_date' => $project->due_date?->toDateString(),
                'is_overdue' => $project->due_date !== null && $project->status->isOpen() && $project->due_date->lt(today()),
                'manager' => UserSummary::from($project->manager),
                'tasks_total' => (int) $project->tasks_count,
                'tasks_done' => (int) $project->done_tasks_count,
                'overdue_tasks' => (int) $project->overdue_tasks_count,
                'percent_complete' => $this->percent((int) $project->done_tasks_count, (int) $project->tasks_count),
                'hours_logged' => $this->decimal($project->hours_logged ?? 0, 2),
                'budget' => $project->budget,
                'currency' => $project->currency,
            ])->all(),
        ];
    }

    private function percent(int $part, int $total): float
    {
        return $total === 0 ? 0.0 : round($part * 100 / $total, 1);
    }

    private function decimal(mixed $value, int $scale): string
    {
        return bcadd((string) ($value ?? '0'), '0', $scale);
    }
}
