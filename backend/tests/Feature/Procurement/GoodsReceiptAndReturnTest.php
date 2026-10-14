<?php

namespace Tests\Feature\Procurement;

use App\Events\Procurement\GoodsReceived;
use App\Events\Procurement\PurchaseReturnConfirmed;
use App\Models\Company;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Procurement\GoodsReceipt;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderLine;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class GoodsReceiptAndReturnTest extends TestCase
{
    private const RECEIVER = ['purchase_orders.view', 'goods_receipts.manage'];

    private Warehouse $warehouse;

    private Product $boltsProduct;

    /**
     * @return array{0: User, 1: Company, 2: PurchaseOrder, 3: PurchaseOrderLine, 4: PurchaseOrderLine}
     */
    private function arrangeApprovedOrder(array $permissions = self::RECEIVER): array
    {
        [$user, $company] = $this->userWith($permissions);
        $this->warehouse = Warehouse::factory()->create(['company_id' => $company->id]);
        $this->boltsProduct = Product::factory()->create(['company_id' => $company->id, 'name' => 'Bolts']);
        $nutsProduct = Product::factory()->create(['company_id' => $company->id, 'name' => 'Nuts']);
        $order = PurchaseOrder::factory()->approved()->withLines([
            ['quantity' => '10', 'unit_price' => '5', 'discount_percent' => '10', 'product_id' => $this->boltsProduct->id, 'description' => 'Bolts'],
            ['quantity' => '4', 'unit_price' => '20', 'product_id' => $nutsProduct->id, 'description' => 'Nuts'],
        ])->create(['company_id' => $company->id, 'warehouse_id' => $this->warehouse->id]);
        $lines = $order->lines()->get();

        return [$user, $company, $order, $lines[0], $lines[1]];
    }

    public function test_creating_a_receipt_snapshots_order_lines(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 14));
        [$user, $company, $order, $bolts] = $this->arrangeApprovedOrder();

        $response = $this->actingAsIn($user, $company)->postJson("/api/v1/purchase-orders/{$order->id}/goods-receipts", [
            'received_date' => '2026-10-14',
            'supplier_delivery_note' => 'DN-77',
            'lines' => [['purchase_order_line_id' => $bolts->id, 'quantity_received' => 6, 'batch_number' => 'B-1', 'expires_at' => '2027-01-01']],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.number', 'GRN-2026-000001')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.warehouse_id', $this->warehouse->id)
            ->assertJsonPath('data.warehouse.name', $this->warehouse->name)
            ->assertJsonPath('data.lines.0.product_id', $this->boltsProduct->id)
            ->assertJsonPath('data.lines.0.product.sku', $this->boltsProduct->sku)
            ->assertJsonPath('data.lines.0.description', 'Bolts')
            ->assertJsonPath('data.lines.0.quantity_ordered', '10.0000')
            ->assertJsonPath('data.lines.0.unit_cost', '4.5000')
            ->assertJsonPath('data.lines.0.expires_at', '2027-01-01');

        $this->assertDatabaseHas('purchase_order_lines', ['id' => $bolts->id, 'quantity_received' => '0.0000']);
    }

    public function test_returns_422_for_empty_payload(): void
    {
        [$user, $company, $order] = $this->arrangeApprovedOrder();

        $this->actingAsIn($user, $company)->postJson("/api/v1/purchase-orders/{$order->id}/goods-receipts", [])
            ->assertUnprocessable()->assertJsonValidationErrors(['received_date', 'lines']);
    }

    public function test_receiving_more_than_remaining_is_rejected_with_422(): void
    {
        [$user, $company, $order, $bolts] = $this->arrangeApprovedOrder();
        $bolts->forceFill(['quantity_received' => '7'])->save();

        $this->actingAsIn($user, $company)->postJson("/api/v1/purchase-orders/{$order->id}/goods-receipts", [
            'received_date' => '2026-10-14',
            'lines' => [['purchase_order_line_id' => $bolts->id, 'quantity_received' => '3.0001']],
        ])->assertUnprocessable()->assertJsonValidationErrors(['lines.0.quantity_received']);

        $this->assertDatabaseCount('goods_receipts', 0);
    }

    public function test_duplicate_lines_cannot_together_exceed_remaining(): void
    {
        [$user, $company, $order, , $nuts] = $this->arrangeApprovedOrder();

        $this->actingAsIn($user, $company)->postJson("/api/v1/purchase-orders/{$order->id}/goods-receipts", [
            'received_date' => '2026-10-14',
            'lines' => [
                ['purchase_order_line_id' => $nuts->id, 'quantity_received' => 3],
                ['purchase_order_line_id' => $nuts->id, 'quantity_received' => 2],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors(['lines.1.quantity_received']);
    }

    public function test_line_from_another_order_is_rejected(): void
    {
        [$user, $company, $order] = $this->arrangeApprovedOrder();
        $otherOrder = PurchaseOrder::factory()->approved()->withLines()->create(['company_id' => $company->id]);
        $foreignLine = $otherOrder->lines()->first();

        $this->actingAsIn($user, $company)->postJson("/api/v1/purchase-orders/{$order->id}/goods-receipts", [
            'received_date' => '2026-10-14',
            'lines' => [['purchase_order_line_id' => $foreignLine->id, 'quantity_received' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['lines.0.purchase_order_line_id']);
    }

    public function test_goods_cannot_be_received_against_a_draft_order(): void
    {
        [$user, $company] = $this->userWith(self::RECEIVER);
        $draft = PurchaseOrder::factory()->withLines()->create(['company_id' => $company->id]);

        $this->actingAsIn($user, $company)->postJson("/api/v1/purchase-orders/{$draft->id}/goods-receipts", [
            'received_date' => '2026-10-14',
            'lines' => [['purchase_order_line_id' => $draft->lines()->first()->id, 'quantity_received' => 1]],
        ])->assertStatus(409);
    }

    public function test_returns_403_without_goods_receipts_permission(): void
    {
        [$user, $company, $order, $bolts] = $this->arrangeApprovedOrder(['purchase_orders.view']);

        $this->actingAsIn($user, $company)->postJson("/api/v1/purchase-orders/{$order->id}/goods-receipts", [
            'received_date' => '2026-10-14',
            'lines' => [['purchase_order_line_id' => $bolts->id, 'quantity_received' => 1]],
        ])->assertForbidden();
        $this->assertDatabaseCount('goods_receipts', 0);
    }

    public function test_returns_404_for_another_companys_order_and_receipt(): void
    {
        [$user, $company] = $this->userWith(self::RECEIVER);
        $foreignOrder = PurchaseOrder::factory()->approved()->withLines()->create();
        $foreignReceipt = GoodsReceipt::factory()->create(['purchase_order_id' => $foreignOrder->id]);

        $this->actingAsIn($user, $company);
        $this->postJson("/api/v1/purchase-orders/{$foreignOrder->id}/goods-receipts", ['received_date' => '2026-10-14', 'lines' => [['purchase_order_line_id' => 1, 'quantity_received' => 1]]])
            ->assertNotFound();
        $this->getJson("/api/v1/goods-receipts/{$foreignReceipt->id}")->assertNotFound();
        $this->postJson("/api/v1/goods-receipts/{$foreignReceipt->id}/confirm")->assertNotFound();
        $this->assertDatabaseHas('goods_receipts', ['id' => $foreignReceipt->id, 'status' => 'draft']);
    }

    public function test_confirming_receipts_increments_received_quantities_and_flips_order_status(): void
    {
        Event::fake([GoodsReceived::class]);
        [$user, $company, $order, $bolts, $nuts] = $this->arrangeApprovedOrder();
        $this->actingAsIn($user, $company);

        $first = $this->postJson("/api/v1/purchase-orders/{$order->id}/goods-receipts", [
            'received_date' => '2026-10-14',
            'lines' => [['purchase_order_line_id' => $bolts->id, 'quantity_received' => 6, 'quantity_rejected' => 1, 'rejection_reason' => 'Bent']],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/goods-receipts/{$first}/confirm")
            ->assertOk()->assertJsonPath('data.status', 'confirmed')->assertJsonPath('data.purchase_order.status', 'partially_received');

        $this->assertDatabaseHas('purchase_order_lines', ['id' => $bolts->id, 'quantity_received' => '6.0000']);
        $this->assertDatabaseHas('purchase_orders', ['id' => $order->id, 'status' => 'partially_received']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'goods.receipts.confirmed', 'entity_id' => $first]);
        Event::assertDispatched(GoodsReceived::class, fn (GoodsReceived $e) => $e->goodsReceipt->id === $first
            && $e->goodsReceipt->relationLoaded('lines')
            && (string) $e->goodsReceipt->lines->first()->quantity_received === '6.0000'
            && $e->goodsReceipt->purchaseOrder->relationLoaded('supplier'));

        $this->postJson("/api/v1/goods-receipts/{$first}/confirm")->assertStatus(409);
        $this->putJson("/api/v1/goods-receipts/{$first}", ['lines' => [['purchase_order_line_id' => $bolts->id, 'quantity_received' => 1]]])->assertStatus(409);

        $second = $this->postJson("/api/v1/purchase-orders/{$order->id}/goods-receipts", [
            'received_date' => '2026-10-15',
            'lines' => [
                ['purchase_order_line_id' => $bolts->id, 'quantity_received' => 4],
                ['purchase_order_line_id' => $nuts->id, 'quantity_received' => 4],
            ],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/goods-receipts/{$second}/confirm")->assertOk()->assertJsonPath('data.purchase_order.status', 'received');

        $this->assertDatabaseHas('purchase_order_lines', ['id' => $bolts->id, 'quantity_received' => '10.0000']);
        $this->assertDatabaseHas('purchase_order_lines', ['id' => $nuts->id, 'quantity_received' => '4.0000']);
        $this->assertDatabaseHas('purchase_orders', ['id' => $order->id, 'status' => 'received']);
        Event::assertDispatchedTimes(GoodsReceived::class, 2);

        $this->postJson("/api/v1/purchase-orders/{$order->id}/goods-receipts", [
            'received_date' => '2026-10-16',
            'lines' => [['purchase_order_line_id' => $nuts->id, 'quantity_received' => 1]],
        ])->assertStatus(409);
    }

    public function test_confirm_rechecks_remaining_when_two_drafts_overlap(): void
    {
        Event::fake([GoodsReceived::class]);
        [$user, $company, $order, , $nuts] = $this->arrangeApprovedOrder();
        $this->actingAsIn($user, $company);
        $body = ['received_date' => '2026-10-14', 'lines' => [['purchase_order_line_id' => $nuts->id, 'quantity_received' => 3]]];

        $a = $this->postJson("/api/v1/purchase-orders/{$order->id}/goods-receipts", $body)->assertCreated()->json('data.id');
        $b = $this->postJson("/api/v1/purchase-orders/{$order->id}/goods-receipts", $body)->assertCreated()->json('data.id');

        $this->postJson("/api/v1/goods-receipts/{$a}/confirm")->assertOk();
        $this->postJson("/api/v1/goods-receipts/{$b}/confirm")->assertUnprocessable()->assertJsonValidationErrors(['lines.0.quantity_received']);

        $this->assertDatabaseHas('purchase_order_lines', ['id' => $nuts->id, 'quantity_received' => '3.0000']);
        $this->assertDatabaseHas('goods_receipts', ['id' => $b, 'status' => 'draft']);
        Event::assertDispatchedTimes(GoodsReceived::class, 1);
    }

    public function test_list_receipts_with_view_permission(): void
    {
        [$user, $company, $order] = $this->arrangeApprovedOrder(['purchase_orders.view']);
        GoodsReceipt::factory()->create(['company_id' => $company->id, 'purchase_order_id' => $order->id]);
        GoodsReceipt::factory()->create();

        $this->actingAsIn($user, $company)->getJson('/api/v1/goods-receipts')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_returning_more_than_received_is_rejected_with_422(): void
    {
        [$user, $company, $order, $bolts] = $this->arrangeApprovedOrder();
        $bolts->forceFill(['quantity_received' => '5', 'quantity_returned' => '2'])->save();

        $this->actingAsIn($user, $company)->postJson('/api/v1/purchase-returns', [
            'purchase_order_id' => $order->id,
            'return_date' => '2026-10-14',
            'reason' => 'damaged',
            'lines' => [['purchase_order_line_id' => $bolts->id, 'quantity' => 4]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['lines.0.quantity']);

        $this->assertDatabaseCount('purchase_returns', 0);
    }

    public function test_purchase_return_returns_422_for_empty_payload_and_foreign_order(): void
    {
        [$user, $company] = $this->userWith(self::RECEIVER);
        $foreignOrder = PurchaseOrder::factory()->approved()->withLines()->create();
        $foreignLineId = $foreignOrder->lines()->first()->id;

        $this->actingAsIn($user, $company)->postJson('/api/v1/purchase-returns', [])
            ->assertUnprocessable()->assertJsonValidationErrors(['purchase_order_id', 'return_date', 'reason', 'lines']);

        $this->postJson('/api/v1/purchase-returns', [
            'purchase_order_id' => $foreignOrder->id, 'return_date' => '2026-10-14', 'reason' => 'other',
            'lines' => [['purchase_order_line_id' => $foreignLineId, 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['purchase_order_id']);
    }

    public function test_confirming_a_return_increments_returned_quantity_and_dispatches_event(): void
    {
        Event::fake([PurchaseReturnConfirmed::class]);
        $this->travelTo(now()->setDate(2026, 10, 14));
        [$user, $company, $order, $bolts] = $this->arrangeApprovedOrder();
        $bolts->forceFill(['quantity_received' => '10'])->save();

        $id = $this->actingAsIn($user, $company)->postJson('/api/v1/purchase-returns', [
            'purchase_order_id' => $order->id,
            'return_date' => '2026-10-14',
            'reason' => 'wrong_item',
            'lines' => [['purchase_order_line_id' => $bolts->id, 'quantity' => 3]],
        ])->assertCreated()
            ->assertJsonPath('data.number', 'PRT-2026-000001')
            ->assertJsonPath('data.reason', 'wrong_item')
            ->assertJsonPath('data.lines.0.unit_cost', '4.5000')
            ->assertJsonPath('data.total', '13.5000')
            ->json('data.id');

        $this->postJson("/api/v1/purchase-returns/{$id}/confirm")->assertOk()->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('purchase_order_lines', ['id' => $bolts->id, 'quantity_returned' => '3.0000']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'purchase.returns.confirmed', 'entity_id' => $id]);
        Event::assertDispatched(PurchaseReturnConfirmed::class, fn (PurchaseReturnConfirmed $e) => $e->purchaseReturn->id === $id && $e->purchaseReturn->relationLoaded('lines'));

        $this->postJson("/api/v1/purchase-returns/{$id}/confirm")->assertStatus(409);
        $this->postJson('/api/v1/purchase-returns', [
            'purchase_order_id' => $order->id, 'return_date' => '2026-10-14', 'reason' => 'excess',
            'lines' => [['purchase_order_line_id' => $bolts->id, 'quantity' => 8]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['lines.0.quantity']);
    }

    public function test_return_goods_receipt_must_belong_to_the_order(): void
    {
        [$user, $company, $order, $bolts] = $this->arrangeApprovedOrder();
        $bolts->forceFill(['quantity_received' => '10'])->save();
        $otherReceipt = GoodsReceipt::factory()->create([
            'purchase_order_id' => PurchaseOrder::factory()->approved()->create(['company_id' => $company->id])->id,
        ]);

        $this->actingAsIn($user, $company)->postJson('/api/v1/purchase-returns', [
            'purchase_order_id' => $order->id, 'goods_receipt_id' => $otherReceipt->id, 'return_date' => '2026-10-14', 'reason' => 'quality',
            'lines' => [['purchase_order_line_id' => $bolts->id, 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['goods_receipt_id']);
    }
}
