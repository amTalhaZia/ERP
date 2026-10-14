<?php

namespace Tests\Feature\Inventory;

use App\Actions\Inventory\RecordStockMovementAction;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockTransfer;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use Tests\Feature\Inventory\Concerns\InteractsWithInventory;
use Tests\TestCase;

class InventoryTenantIsolationTest extends TestCase
{
    use InteractsWithInventory;

    /**
     * @return array{0: User, 1: Company, 2: Company}
     */
    private function arrange(): array
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $userA = User::factory()->inCompany($companyA, $this->allInventoryPermissions)->create();

        return [$userA, $companyA, $companyB];
    }

    public function test_returns_404_for_another_companys_product_on_read_update_delete_and_stock(): void
    {
        [$userA, $companyA, $companyB] = $this->arrange();
        $productB = Product::factory()->create(['company_id' => $companyB->id, 'name' => 'Secret']);

        $this->actingAsIn($userA, $companyA);
        $this->getJson("/api/v1/products/{$productB->id}")->assertNotFound()->assertJsonPath('code', 'not_found');
        $this->putJson("/api/v1/products/{$productB->id}", ['name' => 'Hijacked'])->assertNotFound();
        $this->deleteJson("/api/v1/products/{$productB->id}")->assertNotFound();
        $this->getJson("/api/v1/products/{$productB->id}/stock")->assertNotFound();
        $this->getJson("/api/v1/products/{$productB->id}/movements")->assertNotFound();

        $this->assertDatabaseHas('products', ['id' => $productB->id, 'name' => 'Secret', 'deleted_at' => null]);
    }

    public function test_product_listing_excludes_other_companies(): void
    {
        [$userA, $companyA, $companyB] = $this->arrange();
        $productA = Product::factory()->create(['company_id' => $companyA->id]);
        $productB = Product::factory()->create(['company_id' => $companyB->id]);

        $this->actingAsIn($userA, $companyA)->getJson('/api/v1/products')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $productA->id)
            ->assertJsonMissing(['id' => $productB->id]);
    }

    public function test_returns_404_for_another_companys_warehouse(): void
    {
        [$userA, $companyA, $companyB] = $this->arrange();
        $warehouseB = Warehouse::factory()->create(['company_id' => $companyB->id, 'name' => 'Theirs']);

        $this->actingAsIn($userA, $companyA);
        $this->getJson("/api/v1/warehouses/{$warehouseB->id}")->assertNotFound();
        $this->putJson("/api/v1/warehouses/{$warehouseB->id}", ['name' => 'Mine'])->assertNotFound();
        $this->deleteJson("/api/v1/warehouses/{$warehouseB->id}")->assertNotFound();
        $this->getJson("/api/v1/warehouses/{$warehouseB->id}/locations")->assertNotFound();

        $this->assertDatabaseHas('warehouses', ['id' => $warehouseB->id, 'name' => 'Theirs', 'deleted_at' => null]);
    }

    public function test_returns_404_for_another_companys_transfer_and_its_actions(): void
    {
        [$userA, $companyA, $companyB] = $this->arrange();
        $transferB = StockTransfer::factory()->create(['company_id' => $companyB->id]);

        $this->actingAsIn($userA, $companyA);
        $this->getJson("/api/v1/stock-transfers/{$transferB->id}")->assertNotFound();
        $this->putJson("/api/v1/stock-transfers/{$transferB->id}", ['notes' => 'x'])->assertNotFound();
        $this->postJson("/api/v1/stock-transfers/{$transferB->id}/ship")->assertNotFound();
        $this->postJson("/api/v1/stock-transfers/{$transferB->id}/cancel")->assertNotFound();
        $this->deleteJson("/api/v1/stock-transfers/{$transferB->id}")->assertNotFound();

        $this->assertDatabaseHas('stock_transfers', ['id' => $transferB->id, 'status' => 'draft']);
    }

    public function test_cannot_receive_into_another_companys_warehouse_or_product(): void
    {
        [$userA, $companyA, $companyB] = $this->arrange();
        $warehouseA = Warehouse::factory()->create(['company_id' => $companyA->id]);
        $productA = Product::factory()->create(['company_id' => $companyA->id]);
        $warehouseB = Warehouse::factory()->create(['company_id' => $companyB->id]);
        $productB = Product::factory()->create(['company_id' => $companyB->id]);

        $this->actingAsIn($userA, $companyA);
        $this->receive($warehouseB, $productA, '1', '1')->assertUnprocessable()->assertJsonValidationErrors(['warehouse_id']);
        $this->receive($warehouseA, $productB, '1', '1')->assertUnprocessable()->assertJsonValidationErrors(['lines.0.product_id']);

        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_warehouse_cannot_reference_another_companys_branch(): void
    {
        [$userA, $companyA, $companyB] = $this->arrange();
        $branchB = Branch::factory()->for($companyB)->create();

        $this->actingAsIn($userA, $companyA)
            ->postJson('/api/v1/warehouses', ['name' => 'X', 'code' => 'X1', 'branch_id' => $branchB->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['branch_id']);

        $this->assertDatabaseMissing('warehouses', ['code' => 'X1']);
    }

    public function test_stock_levels_and_valuation_exclude_other_companies(): void
    {
        [$userA, $companyA, $companyB] = $this->arrange();
        [$userB] = $this->userWith($this->allInventoryPermissions, $companyB);
        $warehouseB = Warehouse::factory()->create(['company_id' => $companyB->id]);
        $productB = Product::factory()->create(['company_id' => $companyB->id]);

        // Company B's stock is recorded through the action directly (Sanctum's
        // request guard caches the first authenticated user for the test app).
        $this->setTenant($companyB);
        $this->actingAs($userB);
        app(RecordStockMovementAction::class)->handle([
            'product_id' => $productB->id, 'warehouse_id' => $warehouseB->id, 'type' => 'receipt', 'quantity' => '5', 'unit_cost' => '10',
        ]);
        $this->clearTenant();
        $this->assertDatabaseHas('stock_levels', ['company_id' => $companyB->id, 'quantity_on_hand' => '5.0000']);

        $this->actingAsIn($userA, $companyA);
        $this->getJson('/api/v1/stock/levels')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/stock/movements')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/inventory/reports/valuation')->assertOk()->assertJsonPath('data.total.stock_value', '0.0000');
    }
}
