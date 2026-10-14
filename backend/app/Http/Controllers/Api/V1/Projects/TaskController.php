<?php

namespace App\Http\Controllers\Api\V1\Projects;

use App\Actions\Projects\MoveTaskAction;
use App\Actions\Projects\SaveTaskAction;
use App\Enums\Projects\TaskStatus;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Projects\MoveTaskRequest;
use App\Http\Requests\Projects\StoreTaskRequest;
use App\Http\Resources\Projects\TaskResource;
use App\Http\Resources\Projects\UserSummary;
use App\Models\Projects\Project;
use App\Models\Projects\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TaskController extends ApiController
{
    private const SORTS = ['title', 'status', 'priority', 'due_date', 'position', 'created_at'];

    /**
     * Tasks of one project. Filters: filter[status|priority|assignee_id|milestone_id|parent_id],
     * due=overdue|today|week|none, from/to (due date), top_level=1.
     */
    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        Gate::authorize('view', $project);

        $query = Task::query()
            ->where('project_id', $project->id)
            ->with(['assignee:'.UserSummary::COLUMNS, 'milestone:id,name'])
            ->withCount(['subtasks', 'comments']);

        if ($request->boolean('top_level')) {
            $query->whereNull('parent_id');
        }

        $this->applyListParams($query, $request);

        return $this->paginated($query->paginate($this->perPage($request)), TaskResource::class);
    }

    /** Tasks assigned to the current user across all projects (open ones unless include_done=1). */
    public function myTasks(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Task::class);

        $query = Task::query()
            ->where('assignee_id', $request->user()->id)
            ->whereHas('project')
            ->with(['project:id,number,name', 'milestone:id,name', 'assignee:'.UserSummary::COLUMNS])
            ->withCount(['subtasks', 'comments']);

        if (! $request->boolean('include_done')) {
            $query->where('status', '!=', TaskStatus::Done->value);
        }

        $this->applyFilters($query, $request, ['project_id']);
        $this->applyListParams($query, $request, 'due_date');

        return $this->paginated($query->paginate($this->perPage($request)), TaskResource::class);
    }

    public function store(StoreTaskRequest $request, Project $project, SaveTaskAction $action): JsonResponse
    {
        Gate::authorize('createTask', $project);

        $task = $action->create($project, $request->validated(), $request->user());

        return (new TaskResource($this->loadDetail($task)))->response()->setStatusCode(201);
    }

    public function storeSubtask(StoreTaskRequest $request, Task $task, SaveTaskAction $action): JsonResponse
    {
        Gate::authorize('update', $task);

        $subtask = $action->create($task->project()->firstOrFail(), $request->validated(), $request->user(), $task);

        return (new TaskResource($this->loadDetail($subtask)))->response()->setStatusCode(201);
    }

    public function show(Task $task): TaskResource
    {
        Gate::authorize('view', $task);

        return new TaskResource($this->loadDetail($task)->load([
            'subtasks.assignee:'.UserSummary::COLUMNS,
            'project:id,number,name',
        ]));
    }

    public function update(StoreTaskRequest $request, Task $task, SaveTaskAction $action): TaskResource
    {
        Gate::authorize('update', $task);

        $task = $action->update($task, $request->validated(), $request->user());

        return new TaskResource($this->loadDetail($task->refresh()));
    }

    public function move(MoveTaskRequest $request, Task $task, MoveTaskAction $action): TaskResource
    {
        Gate::authorize('update', $task);

        $task = $action->handle($task, TaskStatus::from($request->input('status')), $request->integer('position'));

        return new TaskResource($this->loadDetail($task));
    }

    /** Deletes the task with its subtasks and comments; time entries keep their hours (task link cleared). */
    public function destroy(Task $task): JsonResponse
    {
        Gate::authorize('delete', $task);

        $task->delete();

        return $this->noContent();
    }

    /**
     * @param  Builder<Task>  $query
     */
    private function applyListParams(Builder $query, Request $request, string $defaultSort = 'position'): void
    {
        $this->applySearch($query, $request, ['title']);
        $this->applyFilters($query, $request, ['status', 'priority', 'assignee_id', 'milestone_id', 'parent_id']);

        match ((string) $request->string('due')) {
            'overdue' => $query->where('tasks.status', '!=', TaskStatus::Done->value)->whereDate('tasks.due_date', '<', today()),
            'today' => $query->whereDate('tasks.due_date', today()),
            'week' => $query->whereBetween('tasks.due_date', [today()->toDateString(), today()->addDays(7)->toDateString()]),
            'none' => $query->whereNull('tasks.due_date'),
            default => null,
        };

        $this->applyDateRange($query, $request, 'due_date');
        $this->applySort($query, $request, self::SORTS, $defaultSort);
    }

    private function loadDetail(Task $task): Task
    {
        return $task->load([
            'assignee:'.UserSummary::COLUMNS,
            'reporter:'.UserSummary::COLUMNS,
            'milestone:id,name',
        ])->loadCount(['subtasks', 'comments']);
    }
}
