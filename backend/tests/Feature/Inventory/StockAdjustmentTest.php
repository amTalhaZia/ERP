<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Product;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Inventory\Concerns\InteractsWithInventory;
use Tests\TestCase;

class StockAdjustmentTest extends TestCase
{
    use InteractsWithInventory;

    public function test_posting_creates_signed_movements_audits_and_is_immutable_afterwards(): void
    {
        [$user, $company, $warehouse, $short] = $this->arrangeStockKeeper();
        $surplus = Product::factory()->create(['company_id' => $company->id, 'cost_price' => '3.0000']);
        $exact = Product::factory()->create(['company_id' => $company->id]);

        $this->actingAsIn($user, $company);
        $this->receive($warehouse, $short, '10', '4.00');
        $this->receive($warehouse, $exact, '5', '1.00');

        $created = $this->postJson('/api/v1/stock-adjustments', [
            'warehouse_id' => $warehouse->id,
            'reason' => 'count',
            'lines' => [
                ['product_id' => $short->id, 'counted_quantity' => '7'],
                ['product_id' => $surplus->id, 'counted_quantity' => '2'],
                ['product_id' => $exact->id, 'counted_quantity' => '5'],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.lines.0.system_quantity', '10.0000')
            ->assertJsonPath('data.lines.0.difference', '-3.0000')
            ->assertJsonPath('data.lines.1.difference', '2.0000');

        $id = $created->json('data.id');
        $this->assertMatchesRegularExpression('/^ADJ-\d{4}-\d{6}$/', $created->json('data.number'));

        $this->postJson("/api/v1/stock-adjustments/{$id}/post")->assertOk()->assertJsonPath('data.status', 'posted');

        $this->assertDatabaseHas('stock_movements', ['reference_type' => 'stock_adjustment', 'reference_id' => $id, 'product_id' => $short->id, 'type' => 'adjustment_out', 'quantity' => '-3.0000', 'unit_cost' => '4.0000', 'balance_after' => '7.0000']);
        $this->assertDatabaseHas('stock_movements', ['reference_type' => 'stock_adjustment', 'reference_id' => $id, 'product_id' => $surplus->id, 'type' => 'adjustment_in', 'quantity' => '2.0000', 'unit_cost' => '3.0000']);
        $this->assertSame(0, DB::table('stock_movements')->where('reference_id', $id)->where('product_id', $exact->id)->where('reference_type', 'stock_adjustment')->count());

        $this->assertSame('7.0000', $this->onHand($short, $warehouse));
        $this->assertSame('2.0000', $this->onHand($surplus, $warehouse));

        $this->assertSame(2, DB::table('audit_logs')->where('action', 'inventory.adjusted')->where('actor_id', $user->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'inventory.adjustment_posted', 'entity_id' => $id]);

        $this->postJson("/api/v1/stock-adjustments/{$id}/post")->assertStatus(409)->assertJsonPath('code', 'conflict');
        $this->putJson("/api/v1/stock-adjustments/{$id}", ['notes' => 'tamper'])->assertStatus(409);
        $this->deleteJson("/api/v1/stock-adjustments/{$id}")->assertStatus(409);

        $this->assertSame(2, DB::table('stock_movements')->where('reference_type', 'stock_adjustment')->count());
        $this->assertDatabaseHas('stock_adjustments', ['id' => $id, 'notes' => null]);
    }

    public function test_draft_adjustment_can_be_deleted(): void
    {
        [$user, $company, $warehouse, $product] = $this->arrangeStockKeeper();

        $this->actingAsIn($user, $company);
        $id = $this->postJson('/api/v1/stock-adjustments', [
            'warehouse_id' => $warehouse->id, 'reason' => 'damage',
            'lines' => [['product_id' => $product->id, 'counted_quantity' => '0']],
        ])->assertCreated()->json('data.id');

        $this->deleteJson("/api/v1/stock-adjustments/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('stock_adjustments', ['id' => $id]);
        $this->assertDatabaseMissing('stock_adjustment_lines', ['stock_adjustment_id' => $id]);
    }
}
