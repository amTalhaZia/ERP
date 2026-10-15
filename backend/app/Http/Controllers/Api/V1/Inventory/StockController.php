<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Actions\Inventory\RecordStockMovementAction;
use App\Enums\Inventory\StockMovementType;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Inventory\IssueStockRequest;
use App\Http\Requests\Inventory\ReceiveStockRequest;
use App\Http\Resources\Inventory\StockLevelResource;
use App\Http\Resources\Inventory\StockMovementResource;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

/**
 * Stock levels, movement history and the ad-hoc receive / issue operations.
 * Every quantity change goes through RecordStockMovementAction.
 */
class StockController extends ApiController
{
    public function __construct(private readonly RecordStockMovementAction $movements) {}

    public function receive(ReceiveStockRequest $request): JsonResponse
    {
        Gate::authorize('receive', StockMovement::class);

        $data = $request->validated();

        $movements = $this->movements->handleMany(
            array_map(fn (array $line) => Arr::only($line, ['product_id', 'quantity', 'unit_cost', 'batch_number', 'manufactured_at', 'expires_at', 'serials']), $data['lines']),
            [
                'warehouse_id' => $data['warehouse_id'],
                'location_id' => $data['location_id'] ?? null,
                'type' => StockMovementType::Receipt,
                'reason' => $data['reason'] ?? null,
                'moved_at' => $data['moved_at'] ?? null,
            ],
        );

        return StockMovementResource::collection($movements->load(['product:id,sku,name', 'warehouse:id,name,code', 'batch:id,batch_number,expires_at']))
            ->response()->setStatusCode(201);
    }

    public function issue(IssueStockRequest $request): JsonResponse
    {
        Gate::authorize('issue', StockMovement::class);

        $data = $request->validated();

        $movements = $this->movements->handleMany(
            array_map(fn (array $line) => Arr::only($line, ['product_id', 'quantity', 'batch_id', 'serials']), $data['lines']),
            [
                'warehouse_id' => $data['warehouse_id'],
                'location_id' => $data['location_id'] ?? null,
                'type' => StockMovementType::Issue,
                'reason' => $data['reason'] ?? null,
                'moved_at' => $data['moved_at'] ?? null,
            ],
        );

        return StockMovementResource::collection($movements->load(['product:id,sku,name', 'warehouse:id,name,code', 'batch:id,batch_number,expires_at']))
            ->response()->setStatusCode(201);
    }

    /** Stock per product × warehouse (× location). */
    public function levels(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', StockLevel::class);

        $query = StockLevel::query()->visibleBranches()->with([
            'product:id,sku,name,uom_id,reorder_level,category_id',
            'product.uom:id,name,symbol',
            'warehouse:id,name,code',
            'location:id,code,name',
        ]);

        $this->applyFilters($query, $request, ['warehouse_id', 'product_id', 'location_id']);

        if ($categoryId = $request->integer('category_id')) {
            $query->whereHas('product', fn (Builder $p) => $p->where('products.category_id', $categoryId));
        }

        if ($request->boolean('below_reorder')) {
            $query->whereHas('product', fn (Builder $p) => $p
                ->where('products.reorder_level', '>', 0)
                ->whereColumn('products.reorder_level', '>=', 'stock_levels.quantity_on_hand'));
        }

        if (($term = trim((string) $request->string('search'))) !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
            $query->whereHas('product', fn (Builder $p) => $p->where(fn (Builder $q) => $q
                ->where('products.sku', 'like', $like)
                ->orWhere('products.name', 'like', $like)));
        }

        $this->applySort($query, $request, ['quantity_on_hand', 'average_cost', 'updated_at', 'product_id', 'warehouse_id'], 'product_id');

        return $this->paginated($query->paginate($this->perPage($request)), StockLevelResource::class);
    }

    /** Company-wide movement history. */
    public function movements(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', StockMovement::class);

        $query = StockMovement::query()->visibleBranches()->with([
            'product:id,sku,name',
            'warehouse:id,name,code',
            'batch:id,batch_number,expires_at',
            'creator:id,name',
        ]);

        $this->applyFilters($query, $request, ['product_id', 'warehouse_id', 'location_id', 'type', 'reference_type', 'reference_id', 'created_by']);
        $this->applyDateRange($query, $request, 'moved_at');
        $this->applySort($query, $request, ['moved_at', 'quantity', 'id'], '-moved_at');

        return $this->paginated($query->paginate($this->perPage($request)), StockMovementResource::class);
    }
}
