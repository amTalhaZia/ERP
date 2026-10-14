<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Inventory\StoreWarehouseRequest;
use App\Http\Resources\Inventory\WarehouseResource;
use App\Models\Inventory\Warehouse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class WarehouseController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Warehouse::class);

        $query = Warehouse::query()->with('branch:id,name')->withCount('locations');

        if (($allowed = app(TenantContext::class)->allowedBranchIds()) !== null) {
            $query->where(fn ($q) => $q->whereNull('branch_id')->orWhereIn('branch_id', $allowed));
        }

        $this->applySearch($query, $request, ['name', 'code', 'city']);
        $this->applyFilters($query, $request, ['is_active', 'is_default', 'branch_id', 'country']);
        $this->applySort($query, $request, ['name', 'code', 'city', 'created_at'], 'name');

        return $this->paginated($query->paginate($this->perPage($request)), WarehouseResource::class);
    }

    /**
     * GET /warehouses/all — unpaginated picker list of active warehouses the
     * user's branch restriction allows (id, name, code, is_default).
     */
    public function all(): JsonResponse
    {
        Gate::authorize('viewAny', Warehouse::class);

        $query = Warehouse::query()->where('is_active', true);

        if (($allowed = app(TenantContext::class)->allowedBranchIds()) !== null) {
            $query->where(fn ($q) => $q->whereNull('branch_id')->orWhereIn('branch_id', $allowed));
        }

        $warehouses = $query->orderByDesc('is_default')->orderBy('name')->orderBy('id')
            ->get(['id', 'name', 'code', 'is_default'])
            ->map(fn (Warehouse $warehouse): array => [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
                'code' => $warehouse->code,
                'is_default' => (bool) $warehouse->is_default,
            ]);

        return response()->json(['data' => $warehouses->values()->all()]);
    }

    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        Gate::authorize('create', Warehouse::class);

        $warehouse = DB::transaction(function () use ($request): Warehouse {
            $data = $request->validated();

            if ($data['is_default'] ?? false) {
                Warehouse::query()->where('is_default', true)->update(['is_default' => false]);
            }

            return Warehouse::query()->create($data);
        });

        return (new WarehouseResource($warehouse->load('branch:id,name')))->response()->setStatusCode(201);
    }

    public function show(Warehouse $warehouse): WarehouseResource
    {
        Gate::authorize('view', $warehouse);

        return new WarehouseResource($warehouse->load('branch:id,name')->loadCount('locations'));
    }

    public function update(StoreWarehouseRequest $request, Warehouse $warehouse): WarehouseResource
    {
        Gate::authorize('update', $warehouse);

        DB::transaction(function () use ($request, $warehouse): void {
            $data = $request->validated();

            if (($data['is_default'] ?? false) && ! $warehouse->is_default) {
                Warehouse::query()->where('is_default', true)->update(['is_default' => false]);
            }

            $warehouse->fill($data)->save();
        });

        return new WarehouseResource($warehouse->refresh()->load('branch:id,name')->loadCount('locations'));
    }

    public function destroy(Warehouse $warehouse): JsonResponse
    {
        Gate::authorize('delete', $warehouse);

        if ($warehouse->stockLevels()->where('quantity_on_hand', '>', 0)->exists()) {
            return $this->message('This warehouse still holds stock; transfer it out first.', ['code' => 'conflict'], 409);
        }

        $warehouse->delete();

        return $this->noContent();
    }
}
