<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Product;
use App\Models\Inventory\StockAdjustment;
use App\Models\Inventory\StockTransfer;
use App\Models\Inventory\Warehouse;
use Tests\Feature\Inventory\Concerns\InteractsWithInventory;
use Tests\TestCase;

class InventoryPermissionTest extends TestCase
{
    use InteractsWithInventory;

    public function test_viewer_cannot_create_products(): void
    {
        [$viewer, $company] = $this->userWith(['products.view']);

        $this->actingAsIn($viewer, $company)
            ->postJson('/api/v1/products', ['sku' => 'NOPE', 'name' => 'Nope'])
            ->assertForbidden()->assertJsonPath('code', 'auth.forbidden');

        $this->assertDatabaseMissing('products', ['sku' => 'NOPE']);
    }

    public function test_user_without_products_view_cannot_list_products(): void
    {
        [$user, $company] = $this->userWith(['warehouses.view']);

        $this->actingAsIn($user, $company)->getJson('/api/v1/products')->assertForbidden();
    }

    public function test_user_without_warehouses_manage_cannot_create_warehouse(): void
    {
        [$user, $company] = $this->userWith(['warehouses.view']);

        $this->actingAsIn($user, $company)->postJson('/api/v1/warehouses', ['name' => 'W', 'code' => 'W1'])->assertForbidden();
        $this->assertDatabaseMissing('warehouses', ['code' => 'W1']);
    }

    public function test_receive_and_issue_require_their_permissions(): void
    {
        [$user, $company] = $this->userWith(['inventory.view']);
        $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);
        $product = Product::factory()->create(['company_id' => $company->id]);

        $this->actingAsIn($user, $company);
        $this->receive($warehouse, $product, '1', '1')->assertForbidden();
        $this->issue($warehouse, $product, '1')->assertForbidden();

        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_stock_views_require_inventory_view(): void
    {
        [$user, $company] = $this->userWith(['products.manage']);

        $this->actingAsIn($user, $company);
        $this->getJson('/api/v1/stock/levels')->assertForbidden();
        $this->getJson('/api/v1/stock/movements')->assertForbidden();
        $this->getJson('/api/v1/inventory/reports/valuation')->assertForbidden();
        $this->getJson('/api/v1/inventory/reports/movement-summary')->assertForbidden();
    }

    public function test_transfer_actions_require_inventory_transfer(): void
    {
        [$user, $company] = $this->userWith(['inventory.view']);
        $transfer = StockTransfer::factory()->create(['company_id' => $company->id]);
        $product = Product::factory()->create(['company_id' => $company->id]);

        $this->actingAsIn($user, $company);
        $this->getJson("/api/v1/stock-transfers/{$transfer->id}")->assertOk();
        $this->postJson('/api/v1/stock-transfers', [
            'from_warehouse_id' => $transfer->from_warehouse_id,
            'to_warehouse_id' => $transfer->to_warehouse_id,
            'lines' => [['product_id' => $product->id, 'quantity' => '1']],
        ])->assertForbidden();
        $this->assertDatabaseCount('stock_transfers', 1);
        $this->postJson("/api/v1/stock-transfers/{$transfer->id}/ship")->assertForbidden();
        $this->postJson("/api/v1/stock-transfers/{$transfer->id}/cancel")->assertForbidden();

        $this->assertDatabaseHas('stock_transfers', ['id' => $transfer->id, 'status' => 'draft']);
    }

    public function test_posting_an_adjustment_requires_inventory_adjust(): void
    {
        [$user, $company] = $this->userWith(['inventory.view']);
        $adjustment = StockAdjustment::factory()->create(['company_id' => $company->id]);

        $this->actingAsIn($user, $company)
            ->postJson("/api/v1/stock-adjustments/{$adjustment->id}/post")
            ->assertForbidden();

        $this->assertDatabaseHas('stock_adjustments', ['id' => $adjustment->id, 'status' => 'draft']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.permission_denied', 'actor_id' => $user->id]);
    }

    public function test_product_validation_errors(): void
    {
        [$user, $company] = $this->userWith(['products.view', 'products.manage']);
        Product::factory()->create(['company_id' => $company->id, 'sku' => 'DUP-1']);

        $this->actingAsIn($user, $company);
        $this->postJson('/api/v1/products', [])->assertUnprocessable()->assertJsonValidationErrors(['sku', 'name']);
        $this->postJson('/api/v1/products', ['sku' => 'DUP-1', 'name' => 'X', 'type' => 'magic', 'cost_price' => -1])
            ->assertUnprocessable()->assertJsonValidationErrors(['sku', 'type', 'cost_price']);
    }

    public function test_transfer_and_adjustment_validation_errors(): void
    {
        [$user, $company] = $this->userWith($this->allInventoryPermissions);
        $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);

        $this->actingAsIn($user, $company);
        $this->postJson('/api/v1/stock-transfers', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['from_warehouse_id', 'to_warehouse_id', 'lines']);
        $this->postJson('/api/v1/stock-transfers', ['from_warehouse_id' => $warehouse->id, 'to_warehouse_id' => $warehouse->id, 'lines' => []])
            ->assertUnprocessable()->assertJsonValidationErrors(['to_warehouse_id', 'lines']);
        $this->postJson('/api/v1/stock-adjustments', ['warehouse_id' => $warehouse->id, 'reason' => 'theft'])
            ->assertUnprocessable()->assertJsonValidationErrors(['reason', 'lines']);
    }

    public function test_manager_can_crud_products_with_audit_trail(): void
    {
        [$user, $company] = $this->userWith(['products.view', 'products.manage']);

        $this->actingAsIn($user, $company);
        $id = $this->postJson('/api/v1/products', [
            'sku' => 'P-100', 'name' => 'Widget', 'cost_price' => '2.50', 'sale_price' => '4', 'reorder_level' => '5',
            'attributes' => ['colour' => 'red'],
        ])->assertCreated()
            ->assertJsonPath('data.cost_price', '2.5000')
            ->assertJsonPath('data.quantity_on_hand', '0.0000')
            ->assertJsonPath('data.attributes.colour', 'red')
            ->json('data.id');

        $this->putJson("/api/v1/products/{$id}", ['name' => 'Widget Pro'])->assertOk()->assertJsonPath('data.name', 'Widget Pro');
        $this->deleteJson("/api/v1/products/{$id}")->assertNoContent();

        $this->assertSoftDeleted('products', ['id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'products.created', 'entity_id' => $id, 'actor_id' => $user->id]);
    }
}
