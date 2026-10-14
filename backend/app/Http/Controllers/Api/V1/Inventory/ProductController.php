<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Enums\Inventory\ProductType;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Inventory\StoreProductRequest;
use App\Http\Resources\Inventory\ProductResource;
use App\Http\Resources\Inventory\StockLevelResource;
use App\Http\Resources\Inventory\StockMovementResource;
use App\Models\Inventory\Batch;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockMovement;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ProductController extends ApiController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Product::class);

        $query = $this->baseQuery();
        $this->applySearch($query, $request, ['sku', 'name', 'barcode']);
        $this->applyFilters($query, $request, ['is_active', 'type', 'category_id', 'uom_id', 'track_batches', 'track_serials']);
        $this->applySort($query, $request, ['sku', 'name', 'sale_price', 'cost_price', 'created_at'], 'name');

        return $this->paginated($query->paginate($this->perPage($request)), ProductResource::class);
    }

    /**
     * Products whose total on-hand is at or below their (non-zero) reorder
     * level. Defaults to active goods; `filter[type]` / `filter[is_active]`
     * override those defaults.
     */
    public function lowStock(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Product::class);

        $filters = (array) $request->input('filter', []);
        $query = $this->baseQuery()
            ->where('products.reorder_level', '>', 0)
            ->whereRaw('COALESCE((SELECT SUM(sl.quantity_on_hand) FROM stock_levels sl WHERE sl.product_id = products.id), 0) <= products.reorder_level');

        if (($filters['type'] ?? '') === '') {
            $query->where('products.type', ProductType::Goods);
        }

        if (($filters['is_active'] ?? '') === '') {
            $query->where('products.is_active', true);
        }

        $this->applySearch($query, $request, ['sku', 'name']);
        $this->applyFilters($query, $request, ['category_id', 'type', 'is_active']);
        $this->applySort($query, $request, ['sku', 'name', 'reorder_level'], 'name');

        return $this->paginated($query->paginate($this->perPage($request)), ProductResource::class);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        Gate::authorize('create', Product::class);

        $product = Product::query()->create($request->validated());

        return (new ProductResource($this->baseQuery()->findOrFail($product->id)))->response()->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        Gate::authorize('view', $product);

        return new ProductResource($this->baseQuery()->findOrFail($product->id));
    }

    public function update(StoreProductRequest $request, Product $product): ProductResource
    {
        Gate::authorize('update', $product);

        $product->fill($request->validated())->save();

        return new ProductResource($this->baseQuery()->findOrFail($product->id));
    }

    public function destroy(Product $product): JsonResponse
    {
        Gate::authorize('delete', $product);

        if ($product->stockLevels()->where('quantity_on_hand', '>', 0)->exists()) {
            return $this->message('This product still has stock on hand; adjust it to zero or deactivate the product instead.', ['code' => 'conflict'], 409);
        }

        $product->delete();

        return $this->noContent();
    }

    public function uploadImage(Request $request, Product $product): ProductResource
    {
        Gate::authorize('update', $product);

        $request->validate([
            'image' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:'.config('erp.uploads.image_max_kb')],
        ]);

        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }

        $path = $request->file('image')->store("companies/{$product->company_id}/products/{$product->id}", 'public');
        $product->forceFill(['image_path' => $path])->save();

        $this->audit->log('products.image_updated', $product);

        return new ProductResource($this->baseQuery()->findOrFail($product->id));
    }

    /** DELETE /products/{product}/image — removes the stored image file and clears the path. */
    public function destroyImage(Product $product): ProductResource
    {
        Gate::authorize('update', $product);

        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
            $product->forceFill(['image_path' => null])->save();

            $this->audit->log('products.image_removed', $product);
        }

        return new ProductResource($this->baseQuery()->findOrFail($product->id));
    }

    /**
     * Batches of one product with expiry and on-hand per warehouse. On-hand is
     * derived from the batch-tagged stock movements (receipts / issues that
     * named the batch); issues that did not name a batch are not attributed
     * to any batch.
     */
    public function batches(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('viewStock', $product);

        $batches = $product->batches()
            ->orderByRaw('expires_at IS NULL')
            ->orderBy('expires_at')
            ->orderBy('batch_number')
            ->get(['id', 'product_id', 'batch_number', 'manufactured_at', 'expires_at']);

        $balances = StockMovement::query()
            ->where('stock_movements.product_id', $product->id)
            ->whereNotNull('stock_movements.batch_id')
            ->join('warehouses', 'warehouses.id', '=', 'stock_movements.warehouse_id')
            ->groupBy('stock_movements.batch_id', 'stock_movements.warehouse_id', 'warehouses.name', 'warehouses.code')
            ->selectRaw('stock_movements.batch_id, stock_movements.warehouse_id, warehouses.name AS warehouse_name, warehouses.code AS warehouse_code, SUM(stock_movements.quantity) AS on_hand')
            ->orderBy('warehouses.name')
            ->toBase()
            ->get()
            ->groupBy('batch_id');

        $today = now()->toDateString();

        return response()->json(['data' => $batches->map(function (Batch $batch) use ($balances, $today): array {
            $warehouses = collect($balances->get($batch->id, []))->map(fn (object $row): array => [
                'warehouse' => ['id' => (int) $row->warehouse_id, 'name' => $row->warehouse_name, 'code' => $row->warehouse_code],
                'on_hand' => Money::normalize((string) $row->on_hand),
            ])->values();

            return [
                'id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'manufactured_at' => $batch->manufactured_at?->toDateString(),
                'expires_at' => $batch->expires_at?->toDateString(),
                'is_expired' => $batch->expires_at !== null && $batch->expires_at->toDateString() < $today,
                'on_hand' => Money::sum($warehouses->pluck('on_hand')->all()),
                'warehouses' => $warehouses->all(),
            ];
        })->values()->all()]);
    }

    /** Stock per warehouse / location for one product. */
    public function stock(Product $product): AnonymousResourceCollection
    {
        Gate::authorize('viewStock', $product);

        $levels = $product->stockLevels()
            ->with(['warehouse:id,name,code', 'location:id,code,name'])
            ->orderBy('warehouse_id')
            ->orderBy('location_id')
            ->get();

        return StockLevelResource::collection($levels);
    }

    /** Paginated movement history for one product. */
    public function movements(Request $request, Product $product): AnonymousResourceCollection
    {
        Gate::authorize('viewStock', $product);

        $query = StockMovement::query()->where('stock_movements.product_id', $product->id)->with(['warehouse:id,name,code', 'batch:id,batch_number,expires_at', 'creator:id,name']);
        $this->applyFilters($query, $request, ['warehouse_id', 'type']);
        $this->applyDateRange($query, $request, 'moved_at');
        $this->applySort($query, $request, ['moved_at', 'id'], '-moved_at');

        return $this->paginated($query->paginate($this->perPage($request)), StockMovementResource::class);
    }

    /**
     * @return Builder<Product>
     */
    private function baseQuery(): Builder
    {
        return Product::query()
            ->with(['category:id,name', 'uom:id,name,symbol'])
            ->withSum('stockLevels as quantity_on_hand', 'quantity_on_hand');
    }
}
