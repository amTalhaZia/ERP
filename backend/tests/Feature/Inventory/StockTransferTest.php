<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Warehouse;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Inventory\Concerns\InteractsWithInventory;
use Tests\TestCase;

class StockTransferTest extends TestCase
{
    use InteractsWithInventory;

    public function test_ship_and_receive_moves_quantity_and_value_between_warehouses(): void
    {
        [$user, $company, $from, $product] = $this->arrangeStockKeeper();
        $to = Warehouse::factory()->create(['company_id' => $company->id]);

        $this->actingAsIn($user, $company);
        $this->receive($from, $product, '10', '5.00');
        $this->receive($from, $product, '10', '7.00');

        $created = $this->postJson('/api/v1/stock-transfers', [
            'from_warehouse_id' => $from->id,
            'to_warehouse_id' => $to->id,
            'notes' => 'Restock branch',
            'lines' => [['product_id' => $product->id, 'quantity' => '8']],
        ])->assertCreated()->assertJsonPath('data.status', 'draft');

        $id = $created->json('data.id');
        $this->assertMatchesRegularExpression('/^TRF-\d{4}-\d{6}$/', $created->json('data.number'));

        $this->postJson("/api/v1/stock-transfers/{$id}/receive")->assertStatus(409);

        $this->postJson("/api/v1/stock-transfers/{$id}/ship")
            ->assertOk()->assertJsonPath('data.status', 'in_transit')->assertJsonPath('data.lines.0.unit_cost', '6.0000');

        $this->assertSame('12.0000', $this->onHand($product, $from));
        $this->assertSame('', $this->onHand($product, $to));

        $this->putJson("/api/v1/stock-transfers/{$id}", ['notes' => 'late edit'])->assertStatus(409);
        $this->postJson("/api/v1/stock-transfers/{$id}/cancel")->assertStatus(409);

        $this->postJson("/api/v1/stock-transfers/{$id}/receive")->assertOk()->assertJsonPath('data.status', 'received');

        $this->assertSame('12.0000', $this->onHand($product, $from));
        $this->assertSame('8.0000', $this->onHand($product, $to));
        $this->assertSame('6.0000', $this->averageCost($product, $to));

        $total = DB::table('stock_levels')->where('product_id', $product->id)->sum('quantity_on_hand');
        $this->assertEquals(20, (float) $total);

        $this->assertDatabaseHas('stock_movements', ['reference_type' => 'stock_transfer', 'reference_id' => $id, 'type' => 'transfer_out', 'quantity' => '-8.0000', 'warehouse_id' => $from->id]);
        $this->assertDatabaseHas('stock_movements', ['reference_type' => 'stock_transfer', 'reference_id' => $id, 'type' => 'transfer_in', 'quantity' => '8.0000', 'unit_cost' => '6.0000', 'warehouse_id' => $to->id]);

        $value = DB::table('stock_movements')->where('reference_id', $id)->where('reference_type', 'stock_transfer')->sum('total_cost');
        $this->assertEquals(0, (float) $value);

        $this->postJson("/api/v1/stock-transfers/{$id}/receive")->assertStatus(409);
    }

    public function test_shipping_more_than_available_fails_with_409_and_stays_draft(): void
    {
        [$user, $company, $from, $product] = $this->arrangeStockKeeper();
        $to = Warehouse::factory()->create(['company_id' => $company->id]);

        $this->actingAsIn($user, $company);
        $this->receive($from, $product, '2', '1');

        $id = $this->postJson('/api/v1/stock-transfers', [
            'from_warehouse_id' => $from->id, 'to_warehouse_id' => $to->id,
            'lines' => [['product_id' => $product->id, 'quantity' => '5']],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/stock-transfers/{$id}/ship")->assertStatus(409)->assertJsonPath('code', 'inventory.insufficient_stock');

        $this->assertDatabaseHas('stock_transfers', ['id' => $id, 'status' => 'draft', 'shipped_at' => null]);
        $this->assertSame('2.0000', $this->onHand($product, $from));
    }

    public function test_draft_can_be_edited_cancelled_but_not_shipped_after_cancel(): void
    {
        [$user, $company, $from, $product] = $this->arrangeStockKeeper();
        $to = Warehouse::factory()->create(['company_id' => $company->id]);

        $this->actingAsIn($user, $company);
        $id = $this->postJson('/api/v1/stock-transfers', [
            'from_warehouse_id' => $from->id, 'to_warehouse_id' => $to->id,
            'lines' => [['product_id' => $product->id, 'quantity' => '1']],
        ])->json('data.id');

        $this->putJson("/api/v1/stock-transfers/{$id}", ['lines' => [['product_id' => $product->id, 'quantity' => '3']]])
            ->assertOk()->assertJsonPath('data.lines.0.quantity', '3.0000')->assertJsonCount(1, 'data.lines');

        $this->postJson("/api/v1/stock-transfers/{$id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->postJson("/api/v1/stock-transfers/{$id}/ship")->assertStatus(409)->assertJsonPath('code', 'conflict');

        $this->assertDatabaseCount('stock_movements', 0);
    }
}
