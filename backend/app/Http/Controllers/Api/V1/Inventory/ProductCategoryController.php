<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Inventory\StoreProductCategoryRequest;
use App\Http\Resources\Inventory\ProductCategoryResource;
use App\Models\Inventory\ProductCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ProductCategoryController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', ProductCategory::class);

        $query = ProductCategory::query()->with('parent:id,name')->withCount(['children', 'products']);
        $this->applySearch($query, $request, ['name', 'code']);
        $this->applyFilters($query, $request, ['is_active', 'parent_id']);
        $this->applySort($query, $request, ['name', 'code', 'created_at'], 'name');

        return $this->paginated($query->paginate($this->perPage($request)), ProductCategoryResource::class);
    }

    /** Full category tree for navigation and selectors. */
    /** Category tree; active categories only unless `include_inactive=1`. */
    public function tree(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ProductCategory::class);

        $categories = ProductCategory::query()
            ->when(! $request->boolean('include_inactive'), fn ($query) => $query->where('is_active', true))
            ->withCount('products')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name', 'code', 'is_active']);

        $byParent = $categories->groupBy('parent_id');

        $build = function (?int $parentId) use (&$build, $byParent): array {
            return $byParent->get($parentId, collect())->map(fn (ProductCategory $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'code' => $c->code,
                'is_active' => (bool) $c->is_active,
                'products_count' => $c->products_count,
                'children' => $build($c->id),
            ])->values()->all();
        };

        return response()->json(['data' => $build(null)]);
    }

    public function store(StoreProductCategoryRequest $request): JsonResponse
    {
        Gate::authorize('create', ProductCategory::class);

        $category = ProductCategory::query()->create($request->validated());

        return (new ProductCategoryResource($category->load('parent:id,name')))->response()->setStatusCode(201);
    }

    public function show(ProductCategory $productCategory): ProductCategoryResource
    {
        Gate::authorize('view', $productCategory);

        return new ProductCategoryResource($productCategory->load('parent:id,name')->loadCount(['children', 'products']));
    }

    public function update(StoreProductCategoryRequest $request, ProductCategory $productCategory): ProductCategoryResource
    {
        Gate::authorize('update', $productCategory);

        $productCategory->fill($request->validated())->save();

        return new ProductCategoryResource($productCategory->refresh()->load('parent:id,name')->loadCount(['children', 'products']));
    }

    public function destroy(ProductCategory $productCategory): JsonResponse
    {
        Gate::authorize('delete', $productCategory);

        if ($productCategory->children()->exists() || $productCategory->products()->exists()) {
            return $this->message('Move child categories and products first.', ['code' => 'conflict'], 409);
        }

        $productCategory->delete();

        return $this->noContent();
    }
}
