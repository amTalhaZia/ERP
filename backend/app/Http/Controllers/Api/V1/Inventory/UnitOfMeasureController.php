<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Inventory\StoreUnitOfMeasureRequest;
use App\Http\Resources\Inventory\UnitOfMeasureResource;
use App\Models\Inventory\UnitOfMeasure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class UnitOfMeasureController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', UnitOfMeasure::class);

        $query = UnitOfMeasure::query()->withCount('products');
        $this->applySearch($query, $request, ['name', 'symbol']);
        $this->applyFilters($query, $request, ['is_active']);
        $this->applySort($query, $request, ['name', 'symbol', 'created_at'], 'name');

        return $this->paginated($query->paginate($this->perPage($request)), UnitOfMeasureResource::class);
    }

    public function store(StoreUnitOfMeasureRequest $request): JsonResponse
    {
        Gate::authorize('create', UnitOfMeasure::class);

        $uom = UnitOfMeasure::query()->create($request->validated());

        return (new UnitOfMeasureResource($uom))->response()->setStatusCode(201);
    }

    public function show(UnitOfMeasure $unitOfMeasure): UnitOfMeasureResource
    {
        Gate::authorize('view', $unitOfMeasure);

        return new UnitOfMeasureResource($unitOfMeasure->loadCount('products'));
    }

    public function update(StoreUnitOfMeasureRequest $request, UnitOfMeasure $unitOfMeasure): UnitOfMeasureResource
    {
        Gate::authorize('update', $unitOfMeasure);

        $unitOfMeasure->fill($request->validated())->save();

        return new UnitOfMeasureResource($unitOfMeasure->refresh()->loadCount('products'));
    }

    public function destroy(UnitOfMeasure $unitOfMeasure): JsonResponse
    {
        Gate::authorize('delete', $unitOfMeasure);

        if ($unitOfMeasure->products()->exists()) {
            return $this->message('This unit is used by products; deactivate it instead.', ['code' => 'conflict'], 409);
        }

        $unitOfMeasure->delete();

        return $this->noContent();
    }
}
