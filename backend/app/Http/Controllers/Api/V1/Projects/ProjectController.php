<?php

namespace App\Http\Controllers\Api\V1\Projects;

use App\Actions\Projects\SaveProjectAction;
use App\Enums\Projects\ProjectStatus;
use App\Enums\Projects\TaskStatus;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Projects\StoreProjectRequest;
use App\Http\Resources\Projects\ProjectResource;
use App\Http\Resources\Projects\UserSummary;
use App\Models\Projects\Project;
use App\Services\Projects\ProjectInsightsService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ProjectController extends ApiController
{
    public function __construct(private readonly ProjectInsightsService $insights) {}

    public function index(Request $request, TenantContext $tenant): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Project::class);

        $query = Project::query()
            ->visibleTo($request->user())
            ->with(['manager:'.UserSummary::COLUMNS, 'department:id,name', 'branch:id,name'])
            ->withCount([
                'members',
                'tasks',
                'tasks as done_tasks_count' => fn (Builder $q) => $q->where('status', TaskStatus::Done->value),
            ]);

        if (($allowed = $tenant->allowedBranchIds()) !== null) {
            $query->where(fn (Builder $q) => $q->whereNull('projects.branch_id')->orWhereIn('projects.branch_id', $allowed));
        }

        $this->applySearch($query, $request, ['name', 'number', 'code']);
        $this->applyFilters($query, $request, ['status', 'priority', 'department_id', 'branch_id', 'manager_id', 'customer_id', 'is_billable']);
        $this->applySort($query, $request, ['name', 'number', 'status', 'priority', 'start_date', 'due_date', 'created_at'], '-created_at');

        return $this->paginated($query->paginate($this->perPage($request)), ProjectResource::class);
    }

    /** Portfolio stats for the projects index page. */
    public function overview(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Project::class);

        $request->validate(['status' => ['nullable', Rule::enum(ProjectStatus::class)]]);

        return response()->json(['data' => $this->insights->overview($request->user(), $request->input('status'))]);
    }

    public function store(StoreProjectRequest $request, SaveProjectAction $action): JsonResponse
    {
        Gate::authorize('create', Project::class);

        $project = $action->create($request->validated(), $request->user());

        return (new ProjectResource($this->loadDetail($project)))->response()->setStatusCode(201);
    }

    public function show(Project $project): ProjectResource
    {
        Gate::authorize('view', $project);

        return new ProjectResource($this->loadDetail($project));
    }

    public function update(StoreProjectRequest $request, Project $project, SaveProjectAction $action): ProjectResource
    {
        Gate::authorize('update', $project);

        $project = $action->update($project, $request->validated());

        return new ProjectResource($this->loadDetail($project));
    }

    public function destroy(Project $project): JsonResponse
    {
        Gate::authorize('delete', $project);

        $project->delete();

        return $this->noContent();
    }

    /** Progress dashboard for one project. */
    public function summary(Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        return response()->json(['data' => $this->insights->summary($project)]);
    }

    public function timeSummary(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return response()->json(['data' => $this->insights->timeSummary($project, $request->date('from'), $request->date('to'))]);
    }

    /** Kanban board: columns per status with ordered top-level tasks. */
    public function board(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        $request->validate([
            'assignee_id' => ['nullable', 'integer'],
            'milestone_id' => ['nullable', 'integer'],
        ]);

        return response()->json(['data' => [
            'project' => ['id' => $project->id, 'number' => $project->number, 'name' => $project->name],
            'columns' => $this->insights->board($project, [
                'assignee_id' => $request->integer('assignee_id') ?: null,
                'milestone_id' => $request->integer('milestone_id') ?: null,
            ]),
        ]]);
    }

    private function loadDetail(Project $project): Project
    {
        return $project->load([
            'manager:'.UserSummary::COLUMNS,
            'department:id,name',
            'branch:id,name',
            'members' => fn ($q) => $q->orderBy('id'),
            'members.user:'.UserSummary::COLUMNS,
        ])->loadCount([
            'tasks',
            'tasks as done_tasks_count' => fn (Builder $q) => $q->where('status', TaskStatus::Done->value),
        ]);
    }
}
