<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Inventory\StoreStorageLocationRequest;
use App\Http\Resources\Inventory\StorageLocationResource;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StorageLocation;
use App\Models\Inventory\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Shallow nested resource: list/create under the warehouse, the rest by id.
 *   GET/POST  warehouses/{warehouse}/locations
 *   GET/PUT/DELETE locations/{location}
 */
class StorageLocationController extends ApiController
{
    public function index(Request $request, Warehouse $warehouse): AnonymousResourceCollection
    {
        Gate::authorize('view', $warehouse);
        Gate::authorize('viewAny', StorageLocation::class);

        $query = StorageLocation::query()->where('storage_locations.warehouse_id', $warehouse->id)->with('parent:id,code,name')->withCount('children');
        $this->applySearch($query, $request, ['code', 'name']);
        $this->applyFilters($query, $request, ['type', 'is_active', 'parent_id']);
        $this->applySort($query, $request, ['code', 'name', 'type', 'created_at'], 'code');

        return $this->paginated($query->paginate($this->perPage($request)), StorageLocationResource::class);
    }

    public function store(StoreStorageLocationRequest $request, Warehouse $warehouse): JsonResponse
    {
        Gate::authorize('update', $warehouse);
        Gate::authorize('create', StorageLocation::class);

        $location = $warehouse->locations()->create($request->validated());

        return (new StorageLocationResource($location->load('parent:id,code,name')))->response()->setStatusCode(201);
    }

    public function show(StorageLocation $location): StorageLocationResource
    {
        Gate::authorize('view', $location);

        return new StorageLocationResource($location->load('parent:id,code,name')->loadCount('children'));
    }

    public function update(StoreStorageLocationRequest $request, StorageLocation $location): StorageLocationResource
    {
        Gate::authorize('update', $location);

        $location->fill($request->validated())->save();

        return new StorageLocationResource($location->refresh()->load('parent:id,code,name')->loadCount('children'));
    }

    public function destroy(StorageLocation $location): JsonResponse
    {
        Gate::authorize('delete', $location);

        $holdsStock = StockLevel::query()->where('location_id', $location->id)->where('quantity_on_hand', '>', 0)->exists();

        if ($location->children()->exists() || $holdsStock) {
            return $this->message('Empty the location and remove its child locations first.', ['code' => 'conflict'], 409);
        }

        $location->delete();

        return $this->noContent();
    }
}
