<?php

namespace Tests\Feature\Sales;

use App\Events\Sales\SalesOrderConfirmed;
use App\Models\Inventory\Warehouse;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderLine;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Sales\Concerns\ArrangesSales;
use Tests\TestCase;

class SalesOrderTest extends TestCase
{
    use ArrangesSales;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-14 09:00:00');
    }

    public function test_creates_draft_order_with_totals_and_shipping_snapshot(): void
    {
        [$user, $company, $customer] = $this->salesUser();

        $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);

        $response = $this->actAs($user, $company)
            ->postJson('/api/v1/sales-orders', $this->documentPayload($customer) + ['warehouse_id' => $warehouse->id]);

        $response->assertCreated()
            ->assertJsonPath('data.number', 'SO-2026-000001')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.total', '207.0000')
            ->assertJsonPath('data.warehouse_id', $warehouse->id)
            ->assertJsonPath('data.warehouse.name', $warehouse->name)
            ->assertJsonPath('data.lines.0.product.sku', 'WIDGET-1')
            ->assertJsonPath('data.shipping_address.name', $customer->name)
            ->assertJsonPath('data.lines.0.quantity_delivered', '0.0000');
    }

    public function test_returns_422_for_empty_payload_and_foreign_customer(): void
    {
        [$user, $company] = $this->salesUser();
        $foreignOrder = SalesOrder::factory()->create();

        $this->actAs($user, $company)->postJson('/api/v1/sales-orders', [])
            ->assertUnprocessable()->assertJsonValidationErrors(['customer_id', 'lines']);

        $this->postJson('/api/v1/sales-orders', [
            'customer_id' => $foreignOrder->customer_id,
            'lines' => [['description' => 'x', 'quantity' => 1, 'unit_price' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['customer_id']);
    }

    public function test_cross_tenant_order_is_404(): void
    {
        [$user, $company] = $this->salesUser();
        $foreign = SalesOrder::factory()->create();

        $this->actAs($user, $company);
        $this->getJson("/api/v1/sales-orders/{$foreign->id}")->assertNotFound();
        $this->putJson("/api/v1/sales-orders/{$foreign->id}", ['notes' => 'x'])->assertNotFound();
        $this->deleteJson("/api/v1/sales-orders/{$foreign->id}")->assertNotFound();
        $this->postJson("/api/v1/sales-orders/{$foreign->id}/confirm")->assertNotFound();
        $this->postJson("/api/v1/sales-orders/{$foreign->id}/deliveries", ['lines' => []])->assertNotFound();
        $this->getJson('/api/v1/sales-orders')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_confirm_dispatches_event_and_order_becomes_read_only(): void
    {
        Event::fake([SalesOrderConfirmed::class]);
        [$user, $company, $customer] = $this->salesUser();
        $this->actAs($user, $company);
        $id = $this->postJson('/api/v1/sales-orders', $this->documentPayload($customer))->json('data.id');

        $this->postJson("/api/v1/sales-orders/{$id}/confirm")->assertOk()->assertJsonPath('data.status', 'confirmed');

        Event::assertDispatched(SalesOrderConfirmed::class, fn (SalesOrderConfirmed $e) => $e->salesOrder->id === $id
            && $e->salesOrder->relationLoaded('lines') && $e->salesOrder->lines->count() === 1);

        $this->putJson("/api/v1/sales-orders/{$id}", ['notes' => 'too late'])->assertStatus(409)->assertJsonPath('code', 'conflict');
        $this->deleteJson("/api/v1/sales-orders/{$id}")->assertStatus(409);
        $this->postJson("/api/v1/sales-orders/{$id}/confirm")->assertStatus(409);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sales_orders.confirmed', 'entity_id' => $id]);
    }

    public function test_approve_requires_the_approve_permission(): void
    {
        [$clerk, $company] = $this->salesUser(['sales_orders.view', 'sales_orders.manage']);
        [$manager] = $this->userWith(['sales_orders.view', 'sales_orders.approve'], $company);
        $order = SalesOrder::factory()->for($company)->confirmed()->create();

        $this->actAs($clerk, $company)->postJson("/api/v1/sales-orders/{$order->id}/approve")->assertForbidden();
        $this->assertDatabaseHas('sales_orders', ['id' => $order->id, 'status' => 'confirmed', 'approved_by' => null]);

        $this->actAs($manager, $company)->postJson("/api/v1/sales-orders/{$order->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approver.id', $manager->id);

        $this->assertDatabaseHas('sales_orders', ['id' => $order->id, 'approved_by' => $manager->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sales_orders.approved', 'entity_id' => $order->id, 'actor_id' => $manager->id]);
    }

    public function test_only_confirmed_orders_can_be_approved(): void
    {
        [$user, $company] = $this->salesUser();
        $draft = SalesOrder::factory()->for($company)->create();

        $this->actAs($user, $company)->postJson("/api/v1/sales-orders/{$draft->id}/approve")->assertStatus(409);
    }

    public function test_cancel_requires_reason_and_is_refused_once_anything_was_delivered(): void
    {
        [$user, $company] = $this->salesUser();
        $open = SalesOrder::factory()->for($company)->approved()->create();
        SalesOrderLine::factory()->for($open)->create(['company_id' => $company->id]);
        $partly = SalesOrder::factory()->for($company)->approved()->create(['status' => 'partially_delivered']);
        SalesOrderLine::factory()->for($partly)->create(['company_id' => $company->id, 'quantity_delivered' => '2']);

        $this->actAs($user, $company);
        $this->postJson("/api/v1/sales-orders/{$open->id}/cancel", [])->assertUnprocessable()->assertJsonValidationErrors(['reason']);
        $this->postJson("/api/v1/sales-orders/{$open->id}/cancel", ['reason' => 'Customer withdrew'])
            ->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.cancelled_reason', 'Customer withdrew');

        $this->postJson("/api/v1/sales-orders/{$partly->id}/cancel", ['reason' => 'x'])->assertStatus(409);
        $this->assertDatabaseHas('sales_orders', ['id' => $partly->id, 'status' => 'partially_delivered']);
    }

    public function test_viewer_without_manage_cannot_create_or_confirm(): void
    {
        [$user, $company, $customer] = $this->salesUser(['sales_orders.view']);
        $order = SalesOrder::factory()->for($company)->create();

        $this->actAs($user, $company);
        $this->postJson('/api/v1/sales-orders', $this->documentPayload($customer))->assertForbidden();
        $this->postJson("/api/v1/sales-orders/{$order->id}/confirm")->assertForbidden();
        $this->getJson("/api/v1/sales-orders/{$order->id}")->assertOk();
        $this->assertDatabaseHas('sales_orders', ['id' => $order->id, 'status' => 'draft']);
    }
}
