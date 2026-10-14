<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Product;
use App\Models\Inventory\ProductCategory;
use App\Models\Inventory\Warehouse;
use Tests\Feature\Inventory\Concerns\InteractsWithInventory;
use Tests\TestCase;

class InventoryReportsTest extends TestCase
{
    use InteractsWithInventory;

    public function test_low_stock_lists_only_active_goods_at_or_below_reorder_level(): void
    {
        [$user, $company, $warehouse] = $this->arrangeStockKeeper();
        $low = Product::factory()->reorderAt('5')->create(['company_id' => $company->id, 'name' => 'Low']);
        $healthy = Product::factory()->reorderAt('5')->create(['company_id' => $company->id, 'name' => 'Healthy']);
        $empty = Product::factory()->reorderAt('1')->create(['company_id' => $company->id, 'name' => 'Empty']);
        Product::factory()->reorderAt('5')->inactive()->create(['company_id' => $company->id]);
        Product::factory()->reorderAt('5')->service()->create(['company_id' => $company->id]);
        Product::factory()->create(['company_id' => $company->id]); // no reorder level

        $this->actingAsIn($user, $company);
        $this->receive($warehouse, $low, '5', '1');
        $this->receive($warehouse, $healthy, '6', '1');

        $response = $this->getJson('/api/v1/products/low-stock')->assertOk()->assertJsonCount(2, 'data');
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$low->id, $empty->id], $ids);
        $this->assertSame('5.0000', collect($response->json('data'))->firstWhere('id', $low->id)['quantity_on_hand']);

        $this->getJson('/api/v1/stock/levels?below_reorder=true')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product_id', $low->id);
    }

    public function test_valuation_equals_on_hand_times_average_cost_per_warehouse_and_total(): void
    {
        [$user, $company, $main, $widget] = $this->arrangeStockKeeper();
        $second = Warehouse::factory()->create(['company_id' => $company->id]);
        $category = ProductCategory::factory()->create(['company_id' => $company->id, 'name' => 'Parts']);
        $gadget = Product::factory()->create(['company_id' => $company->id, 'category_id' => $category->id]);

        $this->actingAsIn($user, $company);
        $this->receive($main, $widget, '10', '5.00');
        $this->receive($main, $widget, '10', '7.00');   // 20 @ 6 = 120
        $this->issue($main, $widget, '5');               // 15 @ 6 = 90
        $this->receive($second, $gadget, '3', '2.5');    // 3 @ 2.5 = 7.5

        $response = $this->getJson('/api/v1/inventory/reports/valuation?group_by=category')->assertOk()
            ->assertJsonPath('data.total.stock_value', '97.5000')
            ->assertJsonPath('data.total.item_count', 2)
            ->assertJsonPath('data.currency', 'USD');

        $byWarehouse = collect($response->json('data.warehouses'))->keyBy('warehouse_id');
        $this->assertSame('90.0000', $byWarehouse[$main->id]['stock_value']);
        $this->assertSame('15.0000', $byWarehouse[$main->id]['quantity_on_hand']);
        $this->assertSame('7.5000', $byWarehouse[$second->id]['stock_value']);

        $byCategory = collect($response->json('data.categories'))->keyBy('category_name');
        $this->assertSame('7.5000', $byCategory['Parts']['stock_value']);
        $this->assertSame('90.0000', $byCategory['Uncategorised']['stock_value']);

        $this->getJson("/api/v1/inventory/reports/valuation?warehouse_id={$second->id}")
            ->assertOk()->assertJsonPath('data.total.stock_value', '7.5000');
    }

    public function test_movement_summary_totals_in_and_out_per_day_and_month(): void
    {
        [$user, $company, $warehouse, $product] = $this->arrangeStockKeeper();

        $this->actingAsIn($user, $company);
        $this->postJson('/api/v1/stock/receive', [
            'warehouse_id' => $warehouse->id, 'moved_at' => '2026-09-01T10:00:00Z',
            'lines' => [['product_id' => $product->id, 'quantity' => '10', 'unit_cost' => '2']],
        ])->assertCreated();
        $this->postJson('/api/v1/stock/issue', [
            'warehouse_id' => $warehouse->id, 'moved_at' => '2026-09-02T10:00:00Z',
            'lines' => [['product_id' => $product->id, 'quantity' => '4']],
        ])->assertCreated();

        $this->getJson('/api/v1/inventory/reports/movement-summary?from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertJsonCount(2, 'data.rows')
            ->assertJsonPath('data.rows.0.period', '2026-09-01')
            ->assertJsonPath('data.rows.0.quantity_in', '10.0000')
            ->assertJsonPath('data.rows.1.quantity_out', '4.0000')
            ->assertJsonPath('data.rows.1.value_out', '8.0000')
            ->assertJsonPath('data.totals.value_in', '20.0000');

        $this->getJson('/api/v1/inventory/reports/movement-summary?group=month&from=2026-09-01&to=2026-09-30')
            ->assertOk()->assertJsonCount(1, 'data.rows')
            ->assertJsonPath('data.rows.0.period', '2026-09')
            ->assertJsonPath('data.rows.0.movements', 2);
    }

    public function test_product_resource_aggregates_quantity_on_hand_across_warehouses(): void
    {
        [$user, $company, $main, $product] = $this->arrangeStockKeeper();
        $second = Warehouse::factory()->create(['company_id' => $company->id]);

        $this->actingAsIn($user, $company);
        $this->receive($main, $product, '4', '1');
        $this->receive($second, $product, '2.5', '1');

        $this->getJson("/api/v1/products/{$product->id}")->assertOk()->assertJsonPath('data.quantity_on_hand', '6.5000');
    }

    public function test_category_tree_nests_children(): void
    {
        [$user, $company] = $this->arrangeStockKeeper();
        $root = ProductCategory::factory()->create(['company_id' => $company->id, 'name' => 'Root']);
        ProductCategory::factory()->create(['company_id' => $company->id, 'name' => 'Child', 'parent_id' => $root->id]);

        $this->actingAsIn($user, $company)->getJson('/api/v1/product-categories/tree')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Root')
            ->assertJsonPath('data.0.children.0.name', 'Child');
    }
}
