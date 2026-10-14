<?php

namespace Tests\Feature\Sales;

use App\Events\Sales\DeliveryConfirmed;
use App\Models\Company;
use App\Models\Sales\Delivery;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderLine;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Sales\Concerns\ArrangesSales;
use Tests\TestCase;

class DeliveryTest extends TestCase
{
    use ArrangesSales;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-14 09:00:00');
    }

    /**
     * @return array{0: User, 1: Company, 2: SalesOrder, 3: SalesOrderLine, 4: SalesOrderLine}
     */
    private function approvedOrder(): array
    {
        [$user, $company] = $this->salesUser();
        $order = SalesOrder::factory()->for($company)->approved()->create();
        $a = SalesOrderLine::factory()->for($order)->create(['company_id' => $company->id, 'line_no' => 1, 'quantity' => '10']);
        $b = SalesOrderLine::factory()->for($order)->create(['company_id' => $company->id, 'line_no' => 2, 'quantity' => '4']);

        return [$user, $company, $order, $a, $b];
    }

    public function test_quantity_cannot_exceed_remaining(): void
    {
        [$user, $company, $order, $a] = $this->approvedOrder();
        $this->actAs($user, $company);

        $this->postJson("/api/v1/sales-orders/{$order->id}/deliveries", [
            'lines' => [['sales_order_line_id' => $a->id, 'quantity' => '10.0001']],
        ])->assertUnprocessable()->assertJsonValidationErrors(['lines.0.quantity']);

        // An open delivery reserves its quantity.
        $this->postJson("/api/v1/sales-orders/{$order->id}/deliveries", [
            'lines' => [['sales_order_line_id' => $a->id, 'quantity' => '6']],
        ])->assertCreated();

        $this->postJson("/api/v1/sales-orders/{$order->id}/deliveries", [
            'lines' => [['sales_order_line_id' => $a->id, 'quantity' => '5']],
        ])->assertUnprocessable()->assertJsonValidationErrors(['lines.0.quantity']);

        $this->assertDatabaseCount('deliveries', 1);
    }

    public function test_lines_of_another_order_and_empty_payload_are_rejected(): void
    {
        [$user, $company, $order] = $this->approvedOrder();
        $otherOrder = SalesOrder::factory()->for($company)->approved()->create();
        $foreignLine = SalesOrderLine::factory()->for($otherOrder)->create(['company_id' => $company->id]);

        $this->actAs($user, $company);
        $this->postJson("/api/v1/sales-orders/{$order->id}/deliveries", [])->assertUnprocessable()->assertJsonValidationErrors(['lines']);
        $this->postJson("/api/v1/sales-orders/{$order->id}/deliveries", [
            'lines' => [['sales_order_line_id' => $foreignLine->id, 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['lines.0.sales_order_line_id']);
    }

    public function test_only_approved_orders_accept_deliveries(): void
    {
        [$user, $company] = $this->salesUser();
        $order = SalesOrder::factory()->for($company)->confirmed()->create();
        $line = SalesOrderLine::factory()->for($order)->create(['company_id' => $company->id]);

        $this->actAs($user, $company)->postJson("/api/v1/sales-orders/{$order->id}/deliveries", [
            'lines' => [['sales_order_line_id' => $line->id, 'quantity' => 1]],
        ])->assertStatus(409);
    }

    public function test_partial_then_full_delivery_updates_order_status_and_fires_event(): void
    {
        Event::fake([DeliveryConfirmed::class]);
        [$user, $company, $order, $a, $b] = $this->approvedOrder();
        $this->actAs($user, $company);

        $first = $this->postJson("/api/v1/sales-orders/{$order->id}/deliveries", [
            'carrier' => 'DHL',
            'lines' => [['sales_order_line_id' => $a->id, 'quantity' => '4']],
        ])->assertCreated()
            ->assertJsonPath('data.number', 'DLV-2026-000001')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.tracking_mode', 'manual')
            ->json('data.id');

        $this->postJson("/api/v1/deliveries/{$first}/ship", ['tracking_number' => 'TRK1'])
            ->assertOk()->assertJsonPath('data.status', 'in_transit')->assertJsonPath('data.tracking_number', 'TRK1');
        $this->postJson("/api/v1/deliveries/{$first}/deliver")->assertOk()->assertJsonPath('data.status', 'delivered');

        $this->assertDatabaseHas('sales_order_lines', ['id' => $a->id, 'quantity_delivered' => '4.0000']);
        $this->assertDatabaseHas('sales_orders', ['id' => $order->id, 'status' => 'partially_delivered']);
        $this->postJson("/api/v1/deliveries/{$first}/deliver")->assertStatus(409);

        $second = $this->postJson("/api/v1/sales-orders/{$order->id}/deliveries", [
            'lines' => [
                ['sales_order_line_id' => $a->id, 'quantity' => '6'],
                ['sales_order_line_id' => $b->id, 'quantity' => '4'],
            ],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/deliveries/{$second}/deliver")->assertOk();

        $this->assertDatabaseHas('sales_order_lines', ['id' => $a->id, 'quantity_delivered' => '10.0000']);
        $this->assertDatabaseHas('sales_order_lines', ['id' => $b->id, 'quantity_delivered' => '4.0000']);
        $this->assertDatabaseHas('sales_orders', ['id' => $order->id, 'status' => 'delivered']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'deliveries.delivered', 'entity_id' => $second]);

        Event::assertDispatchedTimes(DeliveryConfirmed::class, 2);
        Event::assertDispatched(DeliveryConfirmed::class, fn (DeliveryConfirmed $e) => $e->delivery->id === $second
            && $e->delivery->salesOrder->status->value === 'delivered'
            && $e->delivery->lines->count() === 2);

        $this->getJson('/api/v1/deliveries')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/v1/deliveries/{$second}")->assertOk()->assertJsonCount(2, 'data.lines');
    }

    public function test_cross_tenant_delivery_is_404_and_viewer_cannot_deliver(): void
    {
        [$user, $company, $order, $a] = $this->approvedOrder();
        $foreign = Delivery::factory()->create();
        $own = Delivery::factory()->create(['sales_order_id' => $order->id, 'company_id' => $company->id]);
        [$viewer] = $this->userWith(['sales_orders.view'], $company);

        $this->actAs($user, $company);
        $this->getJson("/api/v1/deliveries/{$foreign->id}")->assertNotFound();
        $this->postJson("/api/v1/deliveries/{$foreign->id}/deliver")->assertNotFound();

        $this->actAs($viewer, $company);
        $this->getJson("/api/v1/deliveries/{$own->id}")->assertOk();
        $this->postJson("/api/v1/deliveries/{$own->id}/deliver")->assertForbidden();
        $this->assertDatabaseHas('deliveries', ['id' => $own->id, 'status' => 'pending']);
    }
}
