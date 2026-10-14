<?php

namespace Tests\Feature\Sales;

use App\Models\Sales\Quotation;
use App\Models\Sales\SalesOrder;
use Tests\Feature\Sales\Concerns\ArrangesSales;
use Tests\TestCase;

class SalesReportTest extends TestCase
{
    use ArrangesSales;

    public function test_performance_by_rep_counts_approved_orders_and_conversion_rate(): void
    {
        $this->travelTo('2026-10-14 09:00:00');
        [$user, $company, $customer] = $this->salesUser(['reports.view']);
        [$rep] = $this->userWith([], $company);

        $base = ['customer_id' => $customer->id, 'sales_rep_id' => $rep->id, 'date' => '2026-09-10'];
        Quotation::factory()->for($company)->create($base + ['status' => 'converted', 'total' => '100']);
        Quotation::factory()->for($company)->create($base + ['status' => 'sent', 'total' => '50']);
        Quotation::factory()->for($company)->create(array_merge($base, ['status' => 'draft', 'total' => '25', 'date' => '2025-01-01'])); // out of range

        $orderBase = ['customer_id' => $customer->id, 'sales_rep_id' => $rep->id, 'order_date' => '2026-09-12'];
        SalesOrder::factory()->for($company)->create($orderBase + ['status' => 'approved', 'total' => '100']);
        SalesOrder::factory()->for($company)->create($orderBase + ['status' => 'delivered', 'total' => '40']);
        SalesOrder::factory()->for($company)->create($orderBase + ['status' => 'draft', 'total' => '999']);

        $this->actAs($user, $company)
            ->getJson('/api/v1/sales/reports/performance?from=2026-09-01&to=2026-09-30&group_by=rep')
            ->assertOk()
            ->assertJsonCount(1, 'data.rows')
            ->assertJsonPath('data.rows.0.rep.id', $rep->id)
            ->assertJsonPath('data.rows.0.rep.name', $rep->name)
            ->assertJsonPath('data.rows.0.quotations.count', 2)
            ->assertJsonPath('data.rows.0.quotations.total', '150.0000')
            ->assertJsonPath('data.rows.0.orders.count', 2)
            ->assertJsonPath('data.rows.0.orders.total', '140.0000')
            ->assertJsonPath('data.rows.0.conversion_rate', '50.00')
            ->assertJsonPath('data.totals.orders.total', '140.0000');

        $this->getJson('/api/v1/sales/reports/performance?from=2026-01-01&to=2026-12-31&group_by=month')
            ->assertOk()->assertJsonPath('data.rows.0.key', '2026-09');

        $this->getJson('/api/v1/sales/reports/performance?group_by=customer')
            ->assertOk()->assertJsonPath('data.rows.0.customer.id', $customer->id);

        $this->getJson('/api/v1/sales/reports/performance?group_by=planet')->assertUnprocessable();
    }

    public function test_reports_require_reports_view(): void
    {
        [$user, $company] = $this->salesUser(['sales_orders.view', 'quotations.view']);

        $this->actAs($user, $company)->getJson('/api/v1/sales/reports/performance')->assertForbidden();
        $this->getJson('/api/v1/sales/reports/pipeline')->assertForbidden();
    }
}
