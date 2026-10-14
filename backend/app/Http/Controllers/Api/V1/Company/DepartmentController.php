<?php

namespace App\Http\Controllers\Api\V1\Company;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Company\StoreDepartmentRequest;
use App\Http\Resources\Company\DepartmentResource;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class DepartmentController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Department::class);

        $query = Department::query()->with(['branch:id,name', 'parent:id,name'])->withCount('children');
        $this->applySearch($query, $request, ['name', 'code']);
        $this->applyFilters($query, $request, ['is_active', 'branch_id', 'parent_id']);
        $this->applySort($query, $request, ['name', 'code', 'created_at'], 'name');

        return $this->paginated($query->paginate($this->perPage($request)), DepartmentResource::class);
    }

    /** Full tree for org charts and selectors. */
    public function tree(): JsonResponse
    {
        Gate::authorize('viewAny', Department::class);

        $departments = Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'parent_id', 'branch_id', 'manager_employee_id', 'name', 'code']);

        $byParent = $departments->groupBy('parent_id');

        $build = function (?int $parentId) use (&$build, $byParent): array {
            return $byParent->get($parentId, collect())->map(fn (Department $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'code' => $d->code,
                'branch_id' => $d->branch_id,
                'manager_employee_id' => $d->manager_employee_id,
                'children' => $build($d->id),
            ])->values()->all();
        };

        return response()->json(['data' => $build(null)]);
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        Gate::authorize('create', Department::class);

        $department = Department::query()->create($request->validated());

        return (new DepartmentResource($department->load(['branch:id,name', 'parent:id,name'])))->response()->setStatusCode(201);
    }

    public function show(Department $department): DepartmentResource
    {
        Gate::authorize('view', $department);

        return new DepartmentResource($department->load(['branch:id,name', 'parent:id,name'])->loadCount('children'));
    }

    public function update(StoreDepartmentRequest $request, Department $department): DepartmentResource
    {
        Gate::authorize('update', $department);

        $department->fill($request->validated())->save();

        return new DepartmentResource($department->refresh()->load(['branch:id,name', 'parent:id,name']));
    }

    public function destroy(Department $department): JsonResponse
    {
        Gate::authorize('delete', $department);

        if ($department->children()->exists()) {
            return $this->message('Move or delete child departments first.', ['code' => 'conflict'], 409);
        }

        $department->delete();

        return $this->noContent();
    }
}
