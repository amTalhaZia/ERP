<?php

namespace App\Http\Controllers\Api\V1\Company;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Company\StoreBranchRequest;
use App\Http\Resources\Company\BranchResource;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class BranchController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Branch::class);

        $query = Branch::query()->withCount('departments');
        $this->applySearch($query, $request, ['name', 'code', 'city']);
        $this->applyFilters($query, $request, ['is_active', 'country']);
        $this->applySort($query, $request, ['name', 'code', 'city', 'created_at'], 'name');

        return $this->paginated($query->paginate($this->perPage($request)), BranchResource::class);
    }

    /** Lightweight list for selectors & the map (all active branches, no pagination). */
    public function all(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Branch::class);

        return BranchResource::collection(
            Branch::query()->where('is_active', true)->orderByDesc('is_head_office')->orderBy('name')->get(),
        );
    }

    public function store(StoreBranchRequest $request): JsonResponse
    {
        Gate::authorize('create', Branch::class);

        $branch = DB::transaction(function () use ($request): Branch {
            $data = $request->validated();

            if ($data['is_head_office'] ?? false) {
                Branch::query()->where('is_head_office', true)->update(['is_head_office' => false]);
            }

            return Branch::query()->create($data);
        });

        return (new BranchResource($branch))->response()->setStatusCode(201);
    }

    public function show(Branch $branch): BranchResource
    {
        Gate::authorize('view', $branch);

        return new BranchResource($branch->loadCount('departments'));
    }

    public function update(StoreBranchRequest $request, Branch $branch): BranchResource
    {
        Gate::authorize('update', $branch);

        DB::transaction(function () use ($request, $branch): void {
            $data = $request->validated();

            if (($data['is_head_office'] ?? false) && ! $branch->is_head_office) {
                Branch::query()->where('is_head_office', true)->update(['is_head_office' => false]);
            }

            $branch->fill($data)->save();
        });

        return new BranchResource($branch->refresh()->loadCount('departments'));
    }

    public function destroy(Branch $branch): JsonResponse
    {
        Gate::authorize('delete', $branch);

        $branch->delete();

        return $this->noContent();
    }
}
