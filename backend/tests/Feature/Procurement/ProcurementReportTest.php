<?php

namespace Tests\Feature\Procurement;

use App\Models\Department;
use App\Models\Procurement\GoodsReceipt;
use App\Models\Procurement\GoodsReceiptLine;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseRequisition;
use App\Models\Procurement\Supplier;
use Tests\TestCase;

class ProcurementReportTest extends TestCase
{
    public function test_reports_require_reports_view_permission(): void
    {
        [$user, $company] = $this->userWith(['purchase_orders.view']);

        $this->actingAsIn($user, $company);
        $this->getJson('/api/v1/procurement/reports/spend')->assertForbidden();
        $this->getJson('/api/v1/procurement/reports/status-summary')->assertForbidden();
        $this->getJson('/api/v1/procurement/reports/supplier-performance')->assertForbidden();
    }

    public function test_spend_groups_committed_orders_and_ignores_drafts_and_other_companies(): void
    {
        [$user, $company] = $this->userWith(['reports.view']);
        $acme = Supplier::factory()->create(['company_id' => $company->id, 'name' => 'Acme']);
        $globex = Supplier::factory()->create(['company_id' => $company->id, 'name' => 'Globex']);
        $department = Department::factory()->create(['company_id' => $company->id, 'name' => 'Operations']);
        $requisition = PurchaseRequisition::factory()->create(['company_id' => $company->id, 'department_id' => $department->id]);

        PurchaseOrder::factory()->approved()->withLines([['quantity' => '2', 'unit_price' => '100']])
            ->create(['company_id' => $company->id, 'supplier_id' => $acme->id, 'order_date' => '2026-09-10', 'requisition_id' => $requisition->id]);
        PurchaseOrder::factory()->approved()->withLines([['quantity' => '1', 'unit_price' => '50']])
            ->create(['company_id' => $company->id, 'supplier_id' => $acme->id, 'order_date' => '2026-10-02']);
        PurchaseOrder::factory()->approved()->withLines([['quantity' => '1', 'unit_price' => '30']])
            ->create(['company_id' => $company->id, 'supplier_id' => $globex->id, 'order_date' => '2026-10-05']);
        PurchaseOrder::factory()->withLines([['quantity' => '1', 'unit_price' => '999']])
            ->create(['company_id' => $company->id, 'supplier_id' => $globex->id, 'order_date' => '2026-10-05']);
        PurchaseOrder::factory()->approved()->withLines([['quantity' => '1', 'unit_price' => '777']])->create(['order_date' => '2026-10-05']);

        $this->actingAsIn($user, $company)->getJson('/api/v1/procurement/reports/spend?group_by=supplier')
            ->assertOk()
            ->assertJsonPath('data.total', '280.0000')
            ->assertJsonPath('data.order_count', 3)
            ->assertJsonPath('data.rows.0.label', 'Acme')
            ->assertJsonPath('data.rows.0.total', '250.0000')
            ->assertJsonPath('data.rows.0.order_count', 2)
            ->assertJsonPath('data.rows.1.total', '30.0000');

        $this->getJson('/api/v1/procurement/reports/spend?group_by=month')
            ->assertOk()
            ->assertJsonPath('data.rows.0.label', '2026-09')
            ->assertJsonPath('data.rows.0.total', '200.0000')
            ->assertJsonPath('data.rows.1.label', '2026-10')
            ->assertJsonPath('data.rows.1.total', '80.0000');

        $this->getJson('/api/v1/procurement/reports/spend?group_by=department')
            ->assertOk()
            ->assertJsonPath('data.rows.0.label', 'Operations')
            ->assertJsonPath('data.rows.0.total', '200.0000')
            ->assertJsonPath('data.rows.1.label', 'Unassigned');

        $this->getJson('/api/v1/procurement/reports/spend?from=2026-10-01&to=2026-10-31')
            ->assertOk()->assertJsonPath('data.total', '80.0000');

        $this->getJson('/api/v1/procurement/reports/spend?group_by=bogus')->assertUnprocessable()->assertJsonValidationErrors(['group_by']);
    }

    public function test_status_summary_reports_counts_approval_time_and_on_time_rate(): void
    {
        [$user, $company] = $this->userWith(['reports.view']);
        PurchaseRequisition::factory()->create([
            'company_id' => $company->id, 'status' => 'approved',
            'submitted_at' => '2026-10-01 08:00:00', 'approved_at' => '2026-10-01 14:00:00',
        ]);
        PurchaseRequisition::factory()->create([
            'company_id' => $company->id, 'status' => 'ordered',
            'submitted_at' => '2026-10-02 08:00:00', 'approved_at' => '2026-10-02 10:00:00',
        ]);
        PurchaseRequisition::factory()->create(['company_id' => $company->id]);

        $onTimeOrder = PurchaseOrder::factory()->approved()->withLines()->create(['company_id' => $company->id, 'order_date' => '2026-10-01', 'expected_date' => '2026-10-10']);
        $lateOrder = PurchaseOrder::factory()->approved()->withLines()->create(['company_id' => $company->id, 'order_date' => '2026-10-01', 'expected_date' => '2026-10-05']);
        GoodsReceipt::factory()->confirmed()->create(['company_id' => $company->id, 'purchase_order_id' => $onTimeOrder->id, 'received_date' => '2026-10-09']);
        GoodsReceipt::factory()->confirmed()->create(['company_id' => $company->id, 'purchase_order_id' => $lateOrder->id, 'received_date' => '2026-10-08']);
        GoodsReceipt::factory()->create(['company_id' => $company->id, 'purchase_order_id' => $lateOrder->id, 'received_date' => '2026-10-01']);

        $response = $this->actingAsIn($user, $company)->getJson('/api/v1/procurement/reports/status-summary')->assertOk();

        $response->assertJsonPath('data.avg_requisition_approval_hours', 4)
            ->assertJsonPath('data.on_time_receipt.receipts', 2)
            ->assertJsonPath('data.on_time_receipt.on_time', 1)
            ->assertJsonPath('data.on_time_receipt.rate', 0.5);

        $orders = collect($response->json('data.purchase_orders'))->keyBy('status');
        $this->assertSame(2, $orders['approved']['count']);
        $this->assertSame('100.0000', $orders['approved']['total']);
        $this->assertSame(0, $orders['draft']['count']);

        $requisitions = collect($response->json('data.requisitions'))->keyBy('status');
        $this->assertSame(1, $requisitions['draft']['count']);
        $this->assertSame(1, $requisitions['ordered']['count']);
    }

    public function test_supplier_performance_reports_lead_time_and_rejection_ratio(): void
    {
        [$user, $company] = $this->userWith(['reports.view']);
        $supplier = Supplier::factory()->create(['company_id' => $company->id, 'name' => 'Acme']);
        $order = PurchaseOrder::factory()->approved()->withLines([['quantity' => '10', 'unit_price' => '10']])
            ->create(['company_id' => $company->id, 'supplier_id' => $supplier->id, 'order_date' => '2026-10-01', 'expected_date' => '2026-10-10']);
        $receipt = GoodsReceipt::factory()->confirmed()->create(['company_id' => $company->id, 'purchase_order_id' => $order->id, 'received_date' => '2026-10-07']);
        GoodsReceiptLine::factory()->create([
            'goods_receipt_id' => $receipt->id,
            'purchase_order_line_id' => $order->lines()->first()->id,
            'quantity_received' => '8', 'quantity_rejected' => '2',
        ]);

        $this->actingAsIn($user, $company)->getJson('/api/v1/procurement/reports/supplier-performance')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Acme')
            ->assertJsonPath('data.0.order_count', 1)
            ->assertJsonPath('data.0.total', '100.0000')
            ->assertJsonPath('data.0.receipt_count', 1)
            ->assertJsonPath('data.0.avg_lead_time_days', 6)
            ->assertJsonPath('data.0.on_time_rate', 1)
            ->assertJsonPath('data.0.quantity_rejected', '2.0000')
            ->assertJsonPath('data.0.rejected_ratio', 0.2);
    }
}
