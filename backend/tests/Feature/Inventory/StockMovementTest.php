<?php

namespace Tests\Feature\Inventory;

use App\Actions\Inventory\RecordStockMovementAction;
use App\Enums\Inventory\StockMovementType;
use App\Exceptions\Inventory\InsufficientStockException;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StorageLocation;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Inventory\Concerns\InteractsWithInventory;
use Tests\TestCase;

class StockMovementTest extends TestCase
{
    use InteractsWithInventory;

    public function test_receive_creates_stock_level_and_movement_with_balance_after(): void
    {
        [$user, $company, $warehouse, $product] = $this->arrangeStockKeeper();

        $this->actingAsIn($user, $company);
        $response = $this->receive($warehouse, $product, '10', '5.00');

        $response->assertCreated()
            ->assertJsonPath('data.0.type', 'receipt')
            ->assertJsonPath('data.0.quantity', '10.0000')
            ->assertJsonPath('data.0.unit_cost', '5.0000')
            ->assertJsonPath('data.0.total_cost', '50.0000')
            ->assertJsonPath('data.0.balance_after', '10.0000');

        $this->assertDatabaseHas('stock_levels', [
            'company_id' => $company->id, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id,
            'quantity_on_hand' => '10.0000', 'average_cost' => '5.0000',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'company_id' => $company->id, 'product_id' => $product->id, 'type' => 'receipt',
            'quantity' => '10.0000', 'balance_after' => '10.0000', 'created_by' => $user->id,
        ]);
    }

    public function test_second_receipt_updates_weighted_average_cost(): void
    {
        [$user, $company, $warehouse, $product] = $this->arrangeStockKeeper();

        $this->actingAsIn($user, $company);
        $this->receive($warehouse, $product, '10', '5.00')->assertCreated();
        $this->receive($warehouse, $product, '10', '7.00')->assertCreated()->assertJsonPath('data.0.balance_after', '20.0000');

        $this->assertSame('20.0000', $this->onHand($product, $warehouse));
        $this->assertSame('6.0000', $this->averageCost($product, $warehouse));
    }

    public function test_issue_is_costed_at_average_and_reduces_on_hand(): void
    {
        [$user, $company, $warehouse, $product] = $this->arrangeStockKeeper();

        $this->actingAsIn($user, $company);
        $this->receive($warehouse, $product, '10', '5.00');
        $this->receive($warehouse, $product, '10', '7.00');

        $this->issue($warehouse, $product, '4')
            ->assertCreated()
            ->assertJsonPath('data.0.type', 'issue')
            ->assertJsonPath('data.0.quantity', '-4.0000')
            ->assertJsonPath('data.0.unit_cost', '6.0000')
            ->assertJsonPath('data.0.total_cost', '-24.0000')
            ->assertJsonPath('data.0.balance_after', '16.0000');

        $this->assertSame('16.0000', $this->onHand($product, $warehouse));
        $this->assertSame('6.0000', $this->averageCost($product, $warehouse));
    }

    public function test_issue_beyond_on_hand_returns_409_and_records_nothing(): void
    {
        [$user, $company, $warehouse, $product] = $this->arrangeStockKeeper();

        $this->actingAsIn($user, $company);
        $this->receive($warehouse, $product, '3', '5.00');

        $this->issue($warehouse, $product, '5')
            ->assertStatus(409)
            ->assertJsonPath('code', 'inventory.insufficient_stock')
            ->assertJsonPath('details.available', '3.0000')
            ->assertJsonPath('details.requested', '5.0000');

        $this->assertSame(0, DB::table('stock_movements')->where('type', 'issue')->count());
        $this->assertSame('3.0000', $this->onHand($product, $warehouse));
    }

    public function test_multi_line_issue_is_atomic_when_one_line_is_short(): void
    {
        [$user, $company, $warehouse, $product] = $this->arrangeStockKeeper();
        $other = Product::factory()->create(['company_id' => $company->id]);

        $this->actingAsIn($user, $company);
        $this->receive($warehouse, $product, '10', '2.00');

        $this->postJson('/api/v1/stock/issue', [
            'warehouse_id' => $warehouse->id,
            'lines' => [
                ['product_id' => $product->id, 'quantity' => '5'],
                ['product_id' => $other->id, 'quantity' => '1'],
            ],
        ])->assertStatus(409);

        $this->assertSame('10.0000', $this->onHand($product, $warehouse));
        $this->assertSame(0, DB::table('stock_movements')->where('type', 'issue')->count());
    }

    public function test_two_sequential_issues_leave_consistent_balances(): void
    {
        [$user, $company, $warehouse, $product] = $this->arrangeStockKeeper();

        $this->actingAsIn($user, $company);
        $this->receive($warehouse, $product, '10', '4.00');

        $this->issue($warehouse, $product, '6')->assertCreated()->assertJsonPath('data.0.balance_after', '4.0000');
        $this->issue($warehouse, $product, '6')->assertStatus(409);
        $this->issue($warehouse, $product, '4')->assertCreated()->assertJsonPath('data.0.balance_after', '0.0000');

        $this->assertSame('0.0000', $this->onHand($product, $warehouse));
        $this->assertSame(1, DB::table('stock_levels')->where('product_id', $product->id)->count());
        $this->assertSame(
            '0.0000',
            (string) DB::table('stock_movements')->where('product_id', $product->id)->selectRaw('CAST(SUM(quantity) AS DECIMAL(18,4)) AS s')->value('s'),
        );
    }

    public function test_service_products_cannot_be_received(): void
    {
        [$user, $company, $warehouse] = $this->arrangeStockKeeper();
        $service = Product::factory()->service()->create(['company_id' => $company->id]);

        $this->actingAsIn($user, $company);
        $this->receive($warehouse, $service, '1', '1')->assertUnprocessable()->assertJsonValidationErrors(['lines.0.product_id']);

        $this->assertSame(0, DB::table('stock_movements')->count());
    }

    public function test_receive_validates_payload(): void
    {
        [$user, $company] = $this->arrangeStockKeeper();

        $this->actingAsIn($user, $company)
            ->postJson('/api/v1/stock/receive', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['warehouse_id', 'lines']);

        $this->postJson('/api/v1/stock/receive', ['warehouse_id' => 1, 'lines' => [['quantity' => '-1']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.product_id', 'lines.0.quantity', 'lines.0.unit_cost']);
    }

    public function test_receive_with_batch_and_serials_then_issue_marks_serials_issued(): void
    {
        [$user, $company, $warehouse, $product] = $this->arrangeStockKeeper(['track_serials' => true, 'track_batches' => true]);

        $this->actingAsIn($user, $company);
        $this->receive($warehouse, $product, '2', '10', [
            'batch_number' => 'LOT-1', 'expires_at' => now()->addYear()->toDateString(), 'serials' => ['A1', 'A2'],
        ])->assertCreated()->assertJsonPath('data.0.batch.batch_number', 'LOT-1');

        $this->assertDatabaseHas('batches', ['product_id' => $product->id, 'batch_number' => 'LOT-1', 'company_id' => $company->id]);
        $this->assertSame(2, DB::table('serial_numbers')->where('product_id', $product->id)->where('status', 'in_stock')->count());

        $this->issue($warehouse, $product, '1')->assertUnprocessable()->assertJsonValidationErrors(['serials']);
        $this->issue($warehouse, $product, '1', ['serials' => ['ZZ']])->assertUnprocessable();
        $this->issue($warehouse, $product, '1', ['serials' => ['A1']])->assertCreated();

        $this->assertDatabaseHas('serial_numbers', ['product_id' => $product->id, 'serial' => 'A1', 'status' => 'issued']);
        $this->assertDatabaseHas('serial_numbers', ['product_id' => $product->id, 'serial' => 'A2', 'status' => 'in_stock']);
    }

    public function test_action_records_location_levels_separately_and_sign_follows_type(): void
    {
        [$user, $company, $warehouse, $product] = $this->arrangeStockKeeper();
        $bin = StorageLocation::factory()->create(['company_id' => $company->id, 'warehouse_id' => $warehouse->id]);
        $this->setTenant($company);
        $this->actingAs($user);

        $action = app(RecordStockMovementAction::class);
        $action->handle(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'type' => 'opening', 'quantity' => '5', 'unit_cost' => '2']);
        $inBin = $action->handle(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'location_id' => $bin->id, 'type' => StockMovementType::Purchase, 'quantity' => '-3', 'unit_cost' => '4', 'reference_type' => 'purchase_receipt', 'reference_id' => 99]);
        $sale = $action->handle(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'location_id' => $bin->id, 'type' => 'sale', 'quantity' => '1', 'reference_type' => 'delivery', 'reference_id' => 7]);

        $this->assertSame('3.0000', $inBin->quantity);
        $this->assertSame('-1.0000', $sale->quantity);
        $this->assertSame('4.0000', $sale->unit_cost);
        $this->assertSame('2.0000', $sale->balance_after);
        $this->assertSame(2, DB::table('stock_levels')->where('product_id', $product->id)->count());
        $this->assertDatabaseHas('stock_movements', ['id' => $sale->id, 'reference_type' => 'delivery', 'reference_id' => 7]);

        $this->expectException(InsufficientStockException::class);
        $action->handle(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'location_id' => $bin->id, 'type' => 'issue', 'quantity' => '3']);
    }

    public function test_movements_are_append_only(): void
    {
        [$user, $company, $warehouse, $product] = $this->arrangeStockKeeper();
        $this->setTenant($company);
        $this->actingAs($user);

        $movement = app(RecordStockMovementAction::class)->handle([
            'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'type' => 'receipt', 'quantity' => '1', 'unit_cost' => '1',
        ]);

        $this->expectException(\LogicException::class);
        $movement->update(['quantity' => '100']);
    }

    public function test_history_endpoints_filter_and_paginate(): void
    {
        [$user, $company, $warehouse, $product] = $this->arrangeStockKeeper();

        $this->actingAsIn($user, $company);
        $this->receive($warehouse, $product, '10', '1');
        $this->issue($warehouse, $product, '2');

        $this->getJson('/api/v1/stock/movements?filter[type]=issue')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.pagination.total', 1);

        $this->getJson("/api/v1/products/{$product->id}/movements")
            ->assertOk()->assertJsonCount(2, 'data');

        $this->getJson("/api/v1/products/{$product->id}/stock")
            ->assertOk()->assertJsonPath('data.0.quantity_on_hand', '8.0000')->assertJsonPath('data.0.warehouse.id', $warehouse->id);

        $this->getJson('/api/v1/stock/levels?search='.$product->sku)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.product.sku', $product->sku)
            ->assertJsonPath('data.0.stock_value', '8.0000');

        $this->assertSame(2, StockMovement::query()->count());
    }
}
