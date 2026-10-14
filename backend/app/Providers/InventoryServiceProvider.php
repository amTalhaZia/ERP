<?php

namespace App\Providers;

use App\Models\Inventory\Product;
use App\Models\Inventory\ProductCategory;
use App\Models\Inventory\StockAdjustment;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockTransfer;
use App\Models\Inventory\StorageLocation;
use App\Models\Inventory\UnitOfMeasure;
use App\Models\Inventory\Warehouse;
use App\Policies\Inventory\ProductCategoryPolicy;
use App\Policies\Inventory\ProductPolicy;
use App\Policies\Inventory\StockAdjustmentPolicy;
use App\Policies\Inventory\StockLevelPolicy;
use App\Policies\Inventory\StockMovementPolicy;
use App\Policies\Inventory\StockTransferPolicy;
use App\Policies\Inventory\StorageLocationPolicy;
use App\Policies\Inventory\UnitOfMeasurePolicy;
use App\Policies\Inventory\WarehousePolicy;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Inventory module wiring. Policies live in App\Policies\Inventory, which
 * Laravel's convention-based discovery (App\Policies\<Model>Policy) does not
 * find for App\Models\Inventory\* models, so they are registered explicitly.
 */
class InventoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    private const POLICIES = [
        UnitOfMeasure::class => UnitOfMeasurePolicy::class,
        ProductCategory::class => ProductCategoryPolicy::class,
        Product::class => ProductPolicy::class,
        Warehouse::class => WarehousePolicy::class,
        StorageLocation::class => StorageLocationPolicy::class,
        StockLevel::class => StockLevelPolicy::class,
        StockMovement::class => StockMovementPolicy::class,
        StockTransfer::class => StockTransferPolicy::class,
        StockAdjustment::class => StockAdjustmentPolicy::class,
    ];

    public function boot(): void
    {
        foreach (self::POLICIES as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // Short, stable morph aliases for stock_movements.reference_type and audit entity_type.
        Relation::morphMap([
            'stock_transfer' => StockTransfer::class,
            'stock_adjustment' => StockAdjustment::class,
            'stock_movement' => StockMovement::class,
            'product' => Product::class,
            'warehouse' => Warehouse::class,
        ]);
    }
}
