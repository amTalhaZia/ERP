<?php

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\HR\StoreDesignationRequest;
use App\Http\Resources\HR\DesignationResource;
use App\Models\HR\Designation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class DesignationController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Designation::class);

        $query = Designation::query()->withCount('employees');
        $this->applySearch($query, $request, ['title', 'code']);
        $this->applyFilters($query, $request, ['is_active', 'level']);
        $this->applySort($query, $request, ['title', 'code', 'level', 'created_at'], 'level');

        return $this->paginated($query->paginate($this->perPage($request)), DesignationResource::class);
    }

    public function store(StoreDesignationRequest $request): JsonResponse
    {
        Gate::authorize('create', Designation::class);

        $designation = Designation::query()->create($request->validated());

        return (new DesignationResource($designation))->response()->setStatusCode(201);
    }

    public function show(Designation $designation): DesignationResource
    {
        Gate::authorize('view', $designation);

        return new DesignationResource($designation->loadCount('employees'));
    }

    public function update(StoreDesignationRequest $request, Designation $designation): DesignationResource
    {
        Gate::authorize('update', $designation);

        $designation->fill($request->validated())->save();

        return new DesignationResource($designation->refresh());
    }

    public function destroy(Designation $designation): JsonResponse
    {
        Gate::authorize('delete', $designation);

        if ($designation->employees()->exists()) {
            return $this->message('Reassign employees holding this designation first.', ['code' => 'conflict'], 409);
        }

        $designation->delete();

        return $this->noContent();
    }
}
