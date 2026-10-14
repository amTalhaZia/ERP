<?php

namespace Tests\Feature\Integration;

use App\Actions\Inventory\RecordStockMovementAction;
use App\Models\Company;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\Warehouse;
use App\Models\Procurement\GoodsReceipt;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseReturn;
use App\Models\Sales\Delivery;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderLine;
use App\Models\User;
use Tests\TestCase;

/**
 * Sales / Procurement → Inventory listeners (app/Listeners/Integration).
 * Runs against ALL migrations (DB_DATABASE=erp_test_integration).
 */
class StockIntegrationTest extends TestCase
{
    private const SALES = ['sales_orders.view', 'sales_orders.manage'];

    private const BUYER = ['purchase_orders.view', 'goods_receipts.manage'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-14 09:00:00');
    }

    private function onHand(Product $product, Warehouse $warehouse): ?StockLevel
    {
        return StockLevel::withoutTenancy()->where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->first();
    }

    private function seedStock(Product $product, Warehouse $warehouse, string $quantity, string $unitCost): void
    {
        app(RecordStockMovementAction::class)->handle([
            'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'type' => 'receipt',
            'quantity' => $quantity, 'unit_cost' => $unitCost, 'reason' => 'Opening',
        ]);
    }

    /**
     * @return array{0: User, 1: Company, 2: Warehouse, 3: Product, 4: Product, 5: SalesOrder, 6: SalesOrderLine, 7: SalesOrderLine}
     */
    private function approvedSalesOrder(bool $withWarehouse = true): array
    {
        [$user, $company] = $this->userWith(self::SALES);
        $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);
        $goods = Product::factory()->create(['company_id' => $company->id, 'sku' => 'GOODS-1']);
        $service = Product::factory()->service()->create(['company_id' => $company->id, 'sku' => 'SVC-1']);
        $order = SalesOrder::factory()->for($company)->approved()->create(['warehouse_id' => $withWarehouse ? $warehouse->id : null]);
        $goodsLine = SalesOrderLine::factory()->for($order)->create(['company_id' => $company->id, 'line_no' => 1, 'quantity' => '5', 'product_id' => $goods->id]);
        $serviceLine = SalesOrderLine::factory()->for($order)->create(['company_id' => $company->id, 'line_no' => 2, 'quantity' => '2', 'product_id' => $service->id]);

        return [$user, $company, $warehouse, $goods, $service, $order, $goodsLine, $serviceLine];
    }

    public function test_delivering_goods_issues_stock_and_records_a_sale_movement_referencing_the_delivery(): void
    {
        [$user, $company, $warehouse, $goods, $service, $order, $goodsLine, $serviceLine] = $this->approvedSalesOrder();
        $this->seedStock($goods, $warehouse, '10', '4.0000');
        $this->actingAsIn($user, $company);

        $deliveryId = $this->postJson("/api/v1/sales-orders/{$order->id}/deliveries", [
            'lines' => [
                ['sales_order_line_id' => $goodsLine->id, 'quantity' => '3'],
                ['sales_order_line_id' => $serviceLine->id, 'quantity' => '2'],
            ],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/deliveries/{$deliveryId}/deliver")
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.lines.0.product.sku', 'GOODS-1');

        $this->assertSame('7.0000', (string) $this->onHand($goods, $warehouse)->quantity_on_hand);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $goods->id,
            'warehouse_id' => $warehouse->id,
            'type' => 'sale',
            'quantity' => '-3.0000',
            'unit_cost' => '4.0000',
            'reference_type' => (new Delivery)->getMorphClass(),
            'reference_id' => $deliveryId,
        ]);
        // Service products never move stock.
        $this->assertDatabaseMissing('stock_movements', ['product_id' => $service->id]);
        $this->assertDatabaseCount('stock_levels', 1);
    }

    public function test_insufficient_stock_returns_409_and_rolls_the_delivery_back(): void
    {
        [$user, $company, $warehouse, $goods, , $order, $goodsLine] = $this->approvedSalesOrder();
        $this->seedStock($goods, $warehouse, '2', '4.0000');
        $this->actingAsIn($user, $company);

        $deliveryId = $this->postJson("/api/v1/sales-orders/{$order->id}/deliveries", [
            'lines' => [['sales_order_line_id' => $goodsLine->id, 'quantity' => '3']],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/deliveries/{$deliveryId}/deliver")
            ->assertStatus(409)
            ->assertJsonPath('code', 'inventory.insufficient_stock');

        $this->assertDatabaseHas('deliveries', ['id' => $deliveryId, 'status' => 'pending']);
        $this->assertDatabaseHas('sales_order_lines', ['id' => $goodsLine->id, 'quantity_delivered' => '0.0000']);
        $this->assertDatabaseHas('sales_orders', ['id' => $order->id, 'status' => 'approved']);
        $this->assertSame('2.0000', (string) $this->onHand($goods, $warehouse)->quantity_on_hand);
        $this->assertDatabaseMissing('stock_movements', ['type' => 'sale']);
    }

    public function test_orders_without_a_warehouse_deliver_without_moving_stock(): void
    {
        [$user, $company, $warehouse, $goods, , $order, $goodsLine] = $this->approvedSalesOrder(withWarehouse: false);
        $this->actingAsIn($user, $company);

        $deliveryId = $this->postJson("/api/v1/sales-orders/{$order->id}/deliveries", [
            'lines' => [['sales_order_line_id' => $goodsLine->id, 'quantity' => '3']],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/deliveries/{$deliveryId}/deliver")->assertOk();

        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertNull($this->onHand($goods, $warehouse));
    }

    public function test_sales_order_show_includes_deliveries_with_lines_and_delivery_list_includes_customer(): void
    {
        [$user, $company, $warehouse, $goods, , $order, $goodsLine] = $this->approvedSalesOrder();
        $this->actingAsIn($user, $company);
        $this->postJson("/api/v1/sales-orders/{$order->id}/deliveries", [
            'lines' => [['sales_order_line_id' => $goodsLine->id, 'quantity' => '1']],
        ])->assertCreated();

        $this->getJson("/api/v1/sales-orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.warehouse.id', $warehouse->id)
            ->assertJsonPath('data.lines.0.product.sku', 'GOODS-1')
            ->assertJsonPath('data.deliveries.0.lines.0.product.id', $goods->id)
            ->assertJsonPath('data.deliveries.0.lines.0.quantity', '1.0000');

        $this->getJson('/api/v1/deliveries')
            ->assertOk()
            ->assertJsonPath('data.0.customer.id', $order->customer_id)
            ->assertJsonPath('data.0.customer.name', $order->customer->name);
    }

    /**
     * @return array{0: User, 1: Company, 2: Warehouse, 3: Product, 4: Product, 5: PurchaseOrder}
     */
    private function approvedPurchaseOrder(): array
    {
        [$user, $company] = $this->userWith(self::BUYER);
        $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);
        $goods = Product::factory()->create(['company_id' => $company->id, 'sku' => 'BOLT-1']);
        $service = Product::factory()->service()->create(['company_id' => $company->id]);
        $order = PurchaseOrder::factory()->approved()->withLines([
            ['quantity' => '10', 'unit_price' => '6', 'product_id' => $goods->id, 'description' => 'Bolts'],
            ['quantity' => '1', 'unit_price' => '50', 'product_id' => $service->id, 'description' => 'Freight'],
        ])->create(['company_id' => $company->id, 'warehouse_id' => $warehouse->id]);

        return [$user, $company, $warehouse, $goods, $service, $order];
    }

    public function test_confirming_a_goods_receipt_receives_accepted_quantity_at_unit_cost(): void
    {
        [$user, $company, $warehouse, $goods, $service, $order] = $this->approvedPurchaseOrder();
        $this->seedStock($goods, $warehouse, '10', '3.0000');
        [$boltLine, $freightLine] = $order->lines()->get()->all();
        $this->actingAsIn($user, $company);

        $receiptId = $this->postJson("/api/v1/purchase-orders/{$order->id}/goods-receipts", [
            'received_date' => '2026-10-14',
            'lines' => [
                ['purchase_order_line_id' => $boltLine->id, 'quantity_received' => 6, 'quantity_rejected' => 1, 'batch_number' => 'LOT-7', 'expires_at' => '2027-06-30'],
                ['purchase_order_line_id' => $freightLine->id, 'quantity_received' => 1],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.warehouse.id', $warehouse->id)
            ->assertJsonPath('data.lines.0.product.sku', 'BOLT-1')
            ->json('data.id');

        $this->postJson("/api/v1/goods-receipts/{$receiptId}/confirm")->assertOk()->assertJsonPath('data.status', 'confirmed');

        // 10 @ 3 + 5 accepted @ 6 → 15 on hand, average 4.
        $level = $this->onHand($goods, $warehouse);
        $this->assertSame('15.0000', (string) $level->quantity_on_hand);
        $this->assertSame('4.0000', (string) $level->average_cost);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $goods->id, 'type' => 'purchase', 'quantity' => '5.0000', 'unit_cost' => '6.0000',
            'reference_type' => (new GoodsReceipt)->getMorphClass(), 'reference_id' => $receiptId,
        ]);
        $this->assertDatabaseHas('batches', ['product_id' => $goods->id, 'batch_number' => 'LOT-7', 'expires_at' => '2027-06-30']);
        $this->assertDatabaseMissing('stock_movements', ['product_id' => $service->id]);

        $this->getJson("/api/v1/products/{$goods->id}/batches")->assertForbidden();
        $this->grantInCompany($viewer = User::factory()->create(), $company, ['inventory.view']);
        $this->actingAsIn($viewer, $company)->getJson("/api/v1/products/{$goods->id}/batches")
            ->assertOk()
            ->assertJsonPath('data.0.batch_number', 'LOT-7')
            ->assertJsonPath('data.0.expires_at', '2027-06-30')
            ->assertJsonPath('data.0.on_hand', '5.0000')
            ->assertJsonPath('data.0.warehouses.0.warehouse.id', $warehouse->id);
    }

    public function test_confirming_a_purchase_return_issues_the_returned_goods(): void
    {
        [$user, $company, $warehouse, $goods, , $order] = $this->approvedPurchaseOrder();
        $boltLine = $order->lines()->first();
        $this->actingAsIn($user, $company);

        $receiptId = $this->postJson("/api/v1/purchase-orders/{$order->id}/goods-receipts", [
            'received_date' => '2026-10-14',
            'lines' => [['purchase_order_line_id' => $boltLine->id, 'quantity_received' => 8]],
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/goods-receipts/{$receiptId}/confirm")->assertOk();

        $returnId = $this->postJson('/api/v1/purchase-returns', [
            'purchase_order_id' => $order->id,
            'goods_receipt_id' => $receiptId,
            'return_date' => '2026-10-14',
            'reason' => 'damaged',
            'lines' => [['purchase_order_line_id' => $boltLine->id, 'quantity' => 3]],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/purchase-returns/{$returnId}/confirm")->assertOk()->assertJsonPath('data.status', 'confirmed');

        $this->assertSame('5.0000', (string) $this->onHand($goods, $warehouse)->quantity_on_hand);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $goods->id, 'type' => 'return_out', 'quantity' => '-3.0000',
            'reference_type' => (new PurchaseReturn)->getMorphClass(), 'reference_id' => $returnId,
        ]);
    }

    public function test_purchase_order_show_includes_warehouse_creator_and_line_products(): void
    {
        [$user, $company, $warehouse, $goods, , $order] = $this->approvedPurchaseOrder();
        $order->forceFill(['created_by' => $user->id])->save();

        $this->actingAsIn($user, $company)->getJson("/api/v1/purchase-orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.warehouse.name', $warehouse->name)
            ->assertJsonPath('data.creator.id', $user->id)
            ->assertJsonPath('data.lines.0.product.sku', $goods->sku);
    }
}
