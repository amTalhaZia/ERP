<?php

namespace Tests\Feature\Inventory\Concerns;

use App\Models\Company;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Testing\TestResponse;

/**
 * Shared arrangement helpers for inventory feature tests.
 *
 * Note: the scoped TenantContext survives between test requests (no kernel
 * flush in tests), so arrange cross-company fixtures BEFORE the first request
 * or call clearTenant().
 */
trait InteractsWithInventory
{
    /** @var array<int, string> */
    protected array $allInventoryPermissions = [
        'products.view', 'products.manage', 'warehouses.view', 'warehouses.manage',
        'inventory.view', 'inventory.receive', 'inventory.issue', 'inventory.transfer', 'inventory.adjust',
    ];

    /**
     * @return array{0: User, 1: Company, 2: Warehouse, 3: Product}
     */
    protected function arrangeStockKeeper(array $productState = []): array
    {
        [$user, $company] = $this->userWith($this->allInventoryPermissions);
        $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);
        $product = Product::factory()->create(['company_id' => $company->id] + $productState);

        return [$user, $company, $warehouse, $product];
    }

    protected function receive(Warehouse $warehouse, Product $product, string $quantity, string $unitCost, array $extra = []): TestResponse
    {
        return $this->postJson('/api/v1/stock/receive', [
            'warehouse_id' => $warehouse->id,
            'reason' => 'Test receipt',
            'lines' => [array_merge(['product_id' => $product->id, 'quantity' => $quantity, 'unit_cost' => $unitCost], $extra)],
        ]);
    }

    protected function issue(Warehouse $warehouse, Product $product, string $quantity, array $extra = []): TestResponse
    {
        return $this->postJson('/api/v1/stock/issue', [
            'warehouse_id' => $warehouse->id,
            'reason' => 'Test issue',
            'lines' => [array_merge(['product_id' => $product->id, 'quantity' => $quantity], $extra)],
        ]);
    }

    protected function setTenant(Company $company): void
    {
        app(TenantContext::class)->set($company, null);
    }

    protected function clearTenant(): void
    {
        app(TenantContext::class)->clear();
    }

    protected function onHand(Product $product, Warehouse $warehouse): string
    {
        return (string) \DB::table('stock_levels')
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->whereNull('location_id')
            ->value('quantity_on_hand');
    }

    protected function averageCost(Product $product, Warehouse $warehouse): string
    {
        return (string) \DB::table('stock_levels')
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->whereNull('location_id')
            ->value('average_cost');
    }
}
