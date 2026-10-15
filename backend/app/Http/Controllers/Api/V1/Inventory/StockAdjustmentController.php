<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Actions\Inventory\PostStockAdjustmentAction;
use App\Actions\Inventory\SaveStockAdjustmentAction;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Inventory\StoreStockAdjustmentRequest;
use App\Http\Resources\Inventory\StockAdjustmentResource;
use App\Models\Inventory\StockAdjustment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class StockAdjustmentController extends ApiController
{
    /** @var array<int, string> */
    private const WITH = ['warehouse:id,name,code', 'lines.product:id,sku,name'];

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', StockAdjustment::class);

        $query = StockAdjustment::query()->visibleBranches()->with('warehouse:id,name,code')->withCount('lines');
        $this->applySearch($query, $request, ['number', 'notes']);
        $this->applyFilters($query, $request, ['status', 'reason', 'warehouse_id']);
        $this->applyDateRange($query, $request, 'created_at');
        $this->applySort($query, $request, ['number', 'status', 'reason', 'created_at', 'posted_at'], '-created_at');

        return $this->paginated($query->paginate($this->perPage($request)), StockAdjustmentResource::class);
    }

    public function store(StoreStockAdjustmentRequest $request, SaveStockAdjustmentAction $action): JsonResponse
    {
        Gate::authorize('create', StockAdjustment::class);

        $adjustment = $action->create($request->validated());

        return (new StockAdjustmentResource($adjustment->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function show(StockAdjustment $stockAdjustment): StockAdjustmentResource
    {
        Gate::authorize('view', $stockAdjustment);

        return new StockAdjustmentResource($stockAdjustment->load(self::WITH));
    }

    public function update(StoreStockAdjustmentRequest $request, StockAdjustment $stockAdjustment, SaveStockAdjustmentAction $action): StockAdjustmentResource
    {
        Gate::authorize('update', $stockAdjustment);

        $adjustment = $action->update($stockAdjustment, $request->validated());

        return new StockAdjustmentResource($adjustment->refresh()->load(self::WITH));
    }

    public function destroy(StockAdjustment $stockAdjustment): JsonResponse
    {
        Gate::authorize('delete', $stockAdjustment);

        if (! $stockAdjustment->isDraft()) {
            return $this->message('Posted adjustments are immutable.', ['code' => 'conflict'], 409);
        }

        $stockAdjustment->delete();

        return $this->noContent();
    }

    public function post(StockAdjustment $stockAdjustment, PostStockAdjustmentAction $action): StockAdjustmentResource
    {
        Gate::authorize('post', $stockAdjustment);

        return new StockAdjustmentResource($action->handle($stockAdjustment)->load(self::WITH));
    }
}
