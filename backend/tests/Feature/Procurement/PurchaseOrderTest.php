<?php

namespace Tests\Feature\Procurement;

use App\Events\Procurement\PurchaseOrderApproved;
use App\Models\Company;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    private const MANAGE = ['purchase_orders.view', 'purchase_orders.manage'];

    /** @return array<string, mixed> */
    private function payload(int $supplierId, array $overrides = []): array
    {
        return $overrides + [
            'supplier_id' => $supplierId,
            'order_date' => '2026-10-14',
            'expected_date' => '2026-10-21',
            'lines' => [
                ['description' => 'Widget', 'quantity' => 3, 'unit_price' => 50, 'discount_percent' => 0, 'tax_rate' => 10, 'uom' => 'pcs'],
            ],
        ];
    }

    public function test_creating_a_purchase_order_computes_exact_totals(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 14));
        [$user, $company] = $this->userWith(self::MANAGE);
        $supplier = Supplier::factory()->create(['company_id' => $company->id, 'currency' => 'USD']);

        $response = $this->actingAsIn($user, $company)->postJson('/api/v1/purchase-orders', $this->payload($supplier->id));

        $response->assertCreated()
            ->assertJsonPath('data.number', 'PO-2026-000001')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.subtotal', '150.0000')
            ->assertJsonPath('data.tax_total', '15.0000')
            ->assertJsonPath('data.discount_total', '0.0000')
            ->assertJsonPath('data.total', '165.0000')
            ->assertJsonPath('data.lines.0.line_subtotal', '150.0000')
            ->assertJsonPath('data.lines.0.tax_amount', '15.0000')
            ->assertJsonPath('data.lines.0.line_total', '165.0000')
            ->assertJsonPath('data.lines.0.quantity_remaining', '3.0000');

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $response->json('data.id'), 'company_id' => $company->id, 'total' => '165.0000', 'created_by' => $user->id,
        ]);
    }

    public function test_discount_and_shipping_flow_into_the_header(): void
    {
        [$user, $company] = $this->userWith(self::MANAGE);
        $supplier = Supplier::factory()->create(['company_id' => $company->id]);

        $this->actingAsIn($user, $company)->postJson('/api/v1/purchase-orders', $this->payload($supplier->id, [
            'shipping_cost' => '20',
            'lines' => [
                ['description' => 'A', 'quantity' => 4, 'unit_price' => 25, 'discount_percent' => 10, 'tax_rate' => 5],
                ['description' => 'B', 'quantity' => '1.5', 'unit_price' => '10.10'],
            ],
        ]))->assertCreated()
            ->assertJsonPath('data.subtotal', '105.1500')
            ->assertJsonPath('data.discount_total', '10.0000')
            ->assertJsonPath('data.tax_total', '4.5000')
            ->assertJsonPath('data.shipping_cost', '20.0000')
            ->assertJsonPath('data.total', '129.6500');
    }

    public function test_returns_422_for_empty_payload(): void
    {
        [$user, $company] = $this->userWith(self::MANAGE);

        $this->actingAsIn($user, $company)->postJson('/api/v1/purchase-orders', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['supplier_id', 'order_date', 'lines']);
    }

    public function test_supplier_from_another_company_is_rejected_with_422(): void
    {
        [$user, $company] = $this->userWith(self::MANAGE);
        $foreign = Supplier::factory()->create();

        $this->actingAsIn($user, $company)->postJson('/api/v1/purchase-orders', $this->payload($foreign->id))
            ->assertUnprocessable()->assertJsonValidationErrors(['supplier_id']);

        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_returns_403_without_manage_permission(): void
    {
        [$user, $company] = $this->userWith(['purchase_orders.view']);
        $supplier = Supplier::factory()->create(['company_id' => $company->id]);

        $this->actingAsIn($user, $company)->postJson('/api/v1/purchase-orders', $this->payload($supplier->id))->assertForbidden();
        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_returns_404_for_another_companys_purchase_order(): void
    {
        [$user, $company] = $this->userWith([...self::MANAGE, 'purchase_orders.approve']);
        $foreign = PurchaseOrder::factory()->submitted()->withLines()->create();
        $supplier = Supplier::factory()->create(['company_id' => $company->id]);

        $this->actingAsIn($user, $company);
        $this->getJson("/api/v1/purchase-orders/{$foreign->id}")->assertNotFound();
        $this->putJson("/api/v1/purchase-orders/{$foreign->id}", $this->payload($supplier->id))->assertNotFound();
        $this->deleteJson("/api/v1/purchase-orders/{$foreign->id}")->assertNotFound();
        $this->postJson("/api/v1/purchase-orders/{$foreign->id}/approve")->assertNotFound();
        $this->getJson('/api/v1/purchase-orders')->assertOk()->assertJsonCount(0, 'data');

        $this->assertDatabaseHas('purchase_orders', ['id' => $foreign->id, 'status' => 'submitted']);
    }

    public function test_draft_can_be_edited_but_not_after_submit(): void
    {
        [$user, $company] = $this->userWith(self::MANAGE);
        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $order = PurchaseOrder::factory()->withLines()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);

        $this->actingAsIn($user, $company)->putJson("/api/v1/purchase-orders/{$order->id}", $this->payload($supplier->id, [
            'lines' => [['description' => 'Changed', 'quantity' => 2, 'unit_price' => 7]],
        ]))->assertOk()->assertJsonPath('data.total', '14.0000')->assertJsonCount(1, 'data.lines');

        $this->postJson("/api/v1/purchase-orders/{$order->id}/submit")->assertOk()->assertJsonPath('data.status', 'submitted');

        $this->putJson("/api/v1/purchase-orders/{$order->id}", $this->payload($supplier->id))
            ->assertStatus(409)->assertJsonPath('code', 'conflict');
        $this->deleteJson("/api/v1/purchase-orders/{$order->id}")->assertStatus(409);
        $this->assertDatabaseHas('purchase_orders', ['id' => $order->id, 'total' => '14.0000']);
    }

    public function test_approve_requires_permission_and_route_middleware(): void
    {
        [$user, $company] = $this->userWith(self::MANAGE);
        $order = PurchaseOrder::factory()->submitted()->withLines()->create(['company_id' => $company->id]);

        $this->actingAsIn($user, $company)->postJson("/api/v1/purchase-orders/{$order->id}/approve")->assertForbidden();
        $this->assertDatabaseHas('purchase_orders', ['id' => $order->id, 'status' => 'submitted']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.permission_denied', 'actor_id' => $user->id]);
    }

    public function test_approving_sets_fields_audits_and_dispatches_the_event(): void
    {
        Event::fake([PurchaseOrderApproved::class]);
        $this->travelTo(now()->setDate(2026, 10, 14)->setTime(10, 30));
        [$approver, $company] = $this->userWith(['purchase_orders.view', 'purchase_orders.approve']);
        $order = PurchaseOrder::factory()->submitted()->withLines()->create(['company_id' => $company->id]);

        $this->actingAsIn($approver, $company)->postJson("/api/v1/purchase-orders/{$order->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by', $approver->id)
            ->assertJsonPath('data.approved_at', now()->toJSON());

        Event::assertDispatched(PurchaseOrderApproved::class, fn (PurchaseOrderApproved $e) => $e->purchaseOrder->is($order)
            && $e->purchaseOrder->relationLoaded('lines') && $e->purchaseOrder->relationLoaded('supplier'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'purchase.orders.approved', 'entity_id' => $order->id, 'actor_id' => $approver->id]);

        $this->postJson("/api/v1/purchase-orders/{$order->id}/approve")->assertStatus(409);
    }

    public function test_draft_cannot_be_approved(): void
    {
        [$approver, $company] = $this->userWith(['purchase_orders.approve']);
        $order = PurchaseOrder::factory()->withLines()->create(['company_id' => $company->id]);

        $this->actingAsIn($approver, $company)->postJson("/api/v1/purchase-orders/{$order->id}/approve")->assertStatus(409);
    }

    public function test_cancel_is_refused_once_goods_are_received(): void
    {
        [$user, $company] = $this->userWith(self::MANAGE);
        $order = PurchaseOrder::factory()->approved()
            ->withLines([['quantity' => '10', 'unit_price' => '5', 'quantity_received' => '4']])
            ->create(['company_id' => $company->id]);

        $this->actingAsIn($user, $company)->postJson("/api/v1/purchase-orders/{$order->id}/cancel", ['reason' => 'Changed mind'])
            ->assertStatus(409)->assertJsonPath('code', 'conflict');

        $this->assertDatabaseHas('purchase_orders', ['id' => $order->id, 'status' => 'approved']);
    }

    public function test_cancel_requires_reason_and_records_it(): void
    {
        [$user, $company] = $this->userWith(self::MANAGE);
        $order = PurchaseOrder::factory()->approved()->withLines()->create(['company_id' => $company->id]);

        $this->actingAsIn($user, $company)->postJson("/api/v1/purchase-orders/{$order->id}/cancel", [])
            ->assertUnprocessable()->assertJsonValidationErrors(['reason']);

        $this->postJson("/api/v1/purchase-orders/{$order->id}/cancel", ['reason' => 'Supplier out of stock'])
            ->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.cancelled_reason', 'Supplier out of stock');
        $this->assertDatabaseHas('audit_logs', ['action' => 'purchase.orders.cancelled', 'entity_id' => $order->id]);
    }

    public function test_send_marks_sent_at_and_close_finalises_the_order(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 14)->setTime(8, 0));
        [$user, $company] = $this->userWith(self::MANAGE);
        $order = PurchaseOrder::factory()->approved()->withLines()->create(['company_id' => $company->id]);
        $draft = PurchaseOrder::factory()->withLines()->create(['company_id' => $company->id]);

        $this->actingAsIn($user, $company)->postJson("/api/v1/purchase-orders/{$order->id}/send")
            ->assertOk()->assertJsonPath('data.status', 'approved')->assertJsonPath('data.sent_at', now()->toJSON());
        $this->assertDatabaseHas('audit_logs', ['action' => 'purchase.orders.sent', 'entity_id' => $order->id]);

        $this->postJson("/api/v1/purchase-orders/{$order->id}/close")->assertOk()->assertJsonPath('data.status', 'closed');
        $this->postJson("/api/v1/purchase-orders/{$draft->id}/close")->assertStatus(409);
        $this->postJson("/api/v1/purchase-orders/{$order->id}/cancel", ['reason' => 'x'])->assertStatus(409);
    }

    public function test_list_filters_by_status_and_supplier(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->inCompany($company, ['purchase_orders.view'])->create();
        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        PurchaseOrder::factory()->approved()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);
        PurchaseOrder::factory()->create(['company_id' => $company->id]);

        $this->actingAsIn($user, $company)->getJson('/api/v1/purchase-orders?filter[status]=approved')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.supplier.id', $supplier->id);
        $this->getJson('/api/v1/purchase-orders')->assertOk()->assertJsonCount(2, 'data');
    }
}
