<?php

namespace Tests\Feature\Dashboard;

use App\Enums\Finance\BillStatus;
use App\Enums\Finance\InvoiceStatus;
use App\Enums\HR\EmployeeStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Finance\Bill;
use App\Models\Finance\Invoice;
use App\Models\Inventory\Product;
use App\Models\Procurement\Supplier;
use App\Models\Sales\Customer;
use App\Models\Sales\SalesOrder;
use App\Models\User;
use Tests\Feature\Dashboard\Concerns\BuildsInsightsData;
use Tests\TestCase;

/**
 * Known scenario (period 2026-10-01 … 2026-10-15, previous 2026-09-16 … 2026-09-30):
 *   Oct 05  sale 1,000 (branch North)   Oct 10 sale 500 (branch South)   Oct 12 rent 300 (North)
 *   Sep 20  sale 800 (North)            Sep 25 rent 200 (North)
 * → revenue 1,500 vs 800 (+87.5%), expenses 300 vs 200 (+50%), net 1,200 vs 600 (+100%).
 * Another company posts 9,999 revenue on Oct 05 which must never be counted.
 */
class DashboardTest extends TestCase
{
    use BuildsInsightsData;

    private const ALL = [
        'dashboard.view', 'reports.financial.view', 'employees.view', 'products.view', 'inventory.view',
        'sales_orders.view', 'invoices.view', 'audit.view',
    ];

    private const RANGE = '/api/v1/dashboard?from=2026-10-01&to=2026-10-15';

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeClock();
    }

    /**
     * @return array{0: User, 1: Company, 2: Branch, 3: Branch}
     */
    private function scenario(array $permissions = self::ALL): array
    {
        [$user, $company] = $this->userWith($permissions);
        $north = $this->branch($company, 'North');
        $south = $this->branch($company, 'South');

        $this->postEntry($company, '2026-10-05', '1010', '4000', '1000.00', $north->id);
        $this->postEntry($company, '2026-10-10', '1010', '4000', '500.00', $south->id);
        $this->postEntry($company, '2026-10-12', '6100', '1010', '300.00', $north->id);
        $this->postEntry($company, '2026-09-20', '1010', '4000', '800.00', $north->id);
        $this->postEntry($company, '2026-09-25', '6100', '1010', '200.00', $north->id);

        $other = Company::factory()->create();
        $this->postEntry($other, '2026-10-05', '1010', '4000', '9999.00');

        return [$user, $company, $north, $south];
    }

    public function test_returns_the_documented_payload_shape(): void
    {
        [$user, $company] = $this->scenario();

        $this->actAs($user, $company)->getJson(self::RANGE)
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'period' => ['from', 'to', 'previous_from', 'previous_to'],
                'currency',
                'modules',
                'metrics' => [
                    'revenue' => ['current', 'previous', 'change_percent'],
                    'expenses', 'net_profit', 'receivables', 'payables', 'employees',
                    'active_products', 'low_stock', 'open_sales_orders', 'pending_approvals',
                ],
                'trend' => [['period', 'revenue', 'expenses']],
                'sales_by_month' => [['period', 'total', 'count']],
                'by_branch' => [['branch_id', 'name', 'revenue', 'expenses']],
                'by_department',
                'recent_invoices',
                'low_stock_items',
                'pending_approvals',
                'recent_activity',
            ]])
            ->assertJsonPath('data.period', ['from' => '2026-10-01', 'to' => '2026-10-15', 'previous_from' => '2026-09-16', 'previous_to' => '2026-09-30'])
            ->assertJsonPath('data.currency', $company->currency)
            ->assertJsonPath('data.modules', ['finance', 'hr', 'inventory', 'sales', 'approvals']);
    }

    public function test_revenue_and_expenses_come_from_posted_journals_with_previous_period_change(): void
    {
        [$user, $company, $north, $south] = $this->scenario();

        $response = $this->actAs($user, $company)->getJson(self::RANGE)->assertOk()
            ->assertJsonPath('data.metrics.revenue', ['current' => '1500.0000', 'previous' => '800.0000', 'change_percent' => 87.5])
            ->assertJsonPath('data.metrics.expenses', ['current' => '300.0000', 'previous' => '200.0000', 'change_percent' => 50])
            ->assertJsonPath('data.metrics.net_profit', ['current' => '1200.0000', 'previous' => '600.0000', 'change_percent' => 100]);

        $trend = collect($response->json('data.trend'));
        $this->assertCount(15, $trend, 'ranges under 62 days use daily buckets');
        $this->assertSame(['period' => '2026-10-05', 'revenue' => '1000.0000', 'expenses' => '0.0000'], $trend->firstWhere('period', '2026-10-05'));
        $this->assertSame(['period' => '2026-10-01', 'revenue' => '0.0000', 'expenses' => '0.0000'], $trend->first());

        $branches = collect($response->json('data.by_branch'))->keyBy('name');
        $this->assertSame(['branch_id' => $north->id, 'name' => 'North', 'revenue' => '1000.0000', 'expenses' => '300.0000'], $branches['North']);
        $this->assertSame(['branch_id' => $south->id, 'name' => 'South', 'revenue' => '500.0000', 'expenses' => '0.0000'], $branches['South']);
    }

    public function test_long_ranges_use_monthly_trend_buckets(): void
    {
        [$user, $company] = $this->scenario();

        $trend = $this->actAs($user, $company)->getJson('/api/v1/dashboard?from=2026-01-01&to=2026-10-15')
            ->assertOk()->json('data.trend');

        $this->assertCount(10, $trend);
        $this->assertSame('2026-01', $trend[0]['period']);
        $this->assertSame(['period' => '2026-09', 'revenue' => '800.0000', 'expenses' => '200.0000'], $trend[8]);
        $this->assertSame(['period' => '2026-10', 'revenue' => '1500.0000', 'expenses' => '300.0000'], $trend[9]);
    }

    public function test_other_tenants_postings_are_never_counted(): void
    {
        [$user, $company] = $this->scenario();
        $other = Company::query()->whereKeyNot($company->id)->latest('id')->firstOrFail();
        [$outsider] = $this->userWith(self::ALL, $other);

        $this->actAs($outsider, $other)->getJson(self::RANGE)->assertOk()
            ->assertJsonPath('data.metrics.revenue.current', '9999.0000')
            ->assertJsonPath('data.metrics.revenue.previous', '0.0000')
            ->assertJsonPath('data.metrics.revenue.change_percent', null);

        $this->actAs($user, $company)->getJson(self::RANGE)->assertOk()
            ->assertJsonPath('data.metrics.revenue.current', '1500.0000');
    }

    public function test_branch_filter_limits_ledger_figures(): void
    {
        [$user, $company, $north] = $this->scenario();

        $this->actAs($user, $company)->getJson(self::RANGE.'&branch_id='.$north->id)->assertOk()
            ->assertJsonPath('data.metrics.revenue.current', '1000.0000')
            ->assertJsonPath('data.metrics.expenses.current', '300.0000')
            ->assertJsonPath('data.metrics.revenue.previous', '800.0000')
            ->assertJsonCount(1, 'data.by_branch')
            ->assertJsonPath('data.by_branch.0.name', 'North');
    }

    public function test_branch_of_another_company_is_rejected(): void
    {
        [$user, $company] = $this->userWith(['dashboard.view']);
        $foreign = Branch::factory()->create(['company_id' => Company::factory()->create()->id]);

        $this->actAs($user, $company)->getJson('/api/v1/dashboard?branch_id='.$foreign->id)
            ->assertStatus(422)->assertJsonValidationErrors('branch_id');
    }

    public function test_metrics_and_sections_are_omitted_without_module_permissions(): void
    {
        [$user, $company] = $this->scenario(['dashboard.view']);

        $data = $this->actAs($user, $company)->getJson(self::RANGE)->assertOk()->json('data');

        $this->assertSame(['approvals'], $data['modules']);
        $this->assertSame(['pending_approvals'], array_keys($data['metrics']));
        $this->assertSame([], $data['trend']);
        foreach (['by_branch', 'by_department', 'recent_invoices', 'low_stock_items', 'recent_activity', 'sales_by_month'] as $section) {
            $this->assertArrayNotHasKey($section, $data);
        }
        $this->assertArrayHasKey('pending_approvals', $data);
    }

    public function test_hr_only_user_sees_headcount_but_no_finance(): void
    {
        [$user, $company] = $this->scenario(['dashboard.view', 'employees.view']);

        $data = $this->actAs($user, $company)->getJson(self::RANGE)->assertOk()->json('data');

        $this->assertSame(['hr', 'approvals'], $data['modules']);
        $this->assertSame(['employees', 'pending_approvals'], array_keys($data['metrics']));
        $this->assertArrayNotHasKey('revenue', $data['metrics']);
    }

    public function test_empty_company_returns_zero_metrics_not_nulls(): void
    {
        [$user, $company] = $this->userWith(self::ALL);

        $metrics = $this->actAs($user, $company)->getJson(self::RANGE)->assertOk()->json('data.metrics');

        $this->assertSame(['current' => '0.0000', 'previous' => '0.0000', 'change_percent' => null], $metrics['revenue']);
        $this->assertSame(['current' => '0.0000', 'previous' => '0.0000', 'change_percent' => null], $metrics['receivables']);
        $this->assertSame(['current' => 0, 'previous' => 0, 'change_percent' => null], $metrics['employees']);
        $this->assertSame(['current' => 0, 'previous' => null, 'change_percent' => null], $metrics['low_stock']);
        $this->assertSame(0, $metrics['open_sales_orders']['current']);
    }

    public function test_receivables_payables_headcount_and_lists(): void
    {
        [$user, $company, $north] = $this->scenario();
        $customer = Customer::factory()->create(['company_id' => $company->id, 'name' => 'Acme Retail']);
        $supplier = Supplier::factory()->create(['company_id' => $company->id]);

        Invoice::factory()->create([
            'company_id' => $company->id, 'customer_id' => $customer->id, 'branch_id' => $north->id, 'number' => 'INV-2026-000001',
            'status' => InvoiceStatus::Approved, 'invoice_date' => '2026-10-03', 'due_date' => '2026-11-02',
            'total' => '1150.0000', 'balance_due' => '1150.0000',
        ]);
        Invoice::factory()->create([
            'company_id' => $company->id, 'customer_id' => $customer->id, 'status' => InvoiceStatus::Draft,
            'invoice_date' => '2026-10-04', 'total' => '50.0000', 'balance_due' => '50.0000',
        ]);
        Bill::factory()->create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'status' => BillStatus::Approved,
            'bill_date' => '2026-09-10', 'due_date' => '2026-10-10', 'total' => '400.0000', 'balance_due' => '400.0000',
        ]);

        $sales = Department::factory()->create(['company_id' => $company->id, 'name' => 'Sales']);
        $ops = Department::factory()->create(['company_id' => $company->id, 'name' => 'Operations']);
        $this->employee($company, ['hired_at' => '2025-01-01', 'department_id' => $sales->id]);
        $this->employee($company, ['hired_at' => '2026-10-10', 'department_id' => $ops->id]);
        $this->employee($company, ['hired_at' => '2024-01-01', 'status' => EmployeeStatus::Terminated, 'terminated_at' => '2026-09-01']);
        $this->employee(Company::factory()->create(), ['hired_at' => '2025-01-01']);

        Product::factory()->reorderAt('10')->create(['company_id' => $company->id, 'sku' => 'LOW-1']);
        Product::factory()->create(['company_id' => $company->id]);
        Product::factory()->inactive()->create(['company_id' => $company->id]);
        SalesOrder::factory()->confirmed()->create(['company_id' => $company->id]);
        SalesOrder::factory()->create(['company_id' => $company->id]);
        AuditLog::query()->create(['company_id' => $company->id, 'action' => 'invoices.approved', 'entity_label' => 'INV-1', 'actor_name' => 'Ann']);
        AuditLog::query()->create(['company_id' => Company::factory()->create()->id, 'action' => 'secret.other', 'actor_name' => 'Eve']);

        $response = $this->actAs($user, $company)->getJson(self::RANGE)->assertOk()
            ->assertJsonPath('data.metrics.receivables', ['current' => '1150.0000', 'previous' => '0.0000', 'change_percent' => null])
            ->assertJsonPath('data.metrics.payables', ['current' => '400.0000', 'previous' => '400.0000', 'change_percent' => 0])
            ->assertJsonPath('data.metrics.employees', ['current' => 2, 'previous' => 1, 'change_percent' => 100])
            ->assertJsonPath('data.metrics.active_products.current', 2)
            ->assertJsonPath('data.metrics.low_stock.current', 1)
            ->assertJsonPath('data.metrics.open_sales_orders.current', 1)
            ->assertJsonPath('data.low_stock_items.0.sku', 'LOW-1')
            ->assertJsonPath('data.low_stock_items.0.on_hand', '0.0000')
            ->assertJsonPath('data.recent_invoices.0.status', 'draft')
            ->assertJsonPath('data.recent_invoices.0.customer', 'Acme Retail')
            ->assertJsonPath('data.recent_invoices.1.number', 'INV-2026-000001')
            ->assertJsonPath('data.recent_invoices.1.total', '1150.0000');

        $departments = collect($response->json('data.by_department'))->pluck('headcount', 'name')->all();
        $this->assertSame(['Operations' => 1, 'Sales' => 1], collect($departments)->sortKeys()->all());

        $actions = collect($response->json('data.recent_activity'))->pluck('action');
        $this->assertContains('invoices.approved', $actions);
        $this->assertNotContains('secret.other', $actions);

        $this->assertSame('1150.0000', collect($response->json('data.sales_by_month'))->firstWhere('period', '2026-10')['total']);
    }

    public function test_defaults_to_month_to_date_in_company_timezone(): void
    {
        [$user, $company] = $this->userWith(['dashboard.view']);

        $this->actAs($user, $company)->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('data.period.from', '2026-10-01')
            ->assertJsonPath('data.period.to', '2026-10-15')
            ->assertJsonPath('data.period.previous_from', '2026-09-16');
    }

    public function test_requires_dashboard_view_permission(): void
    {
        [$user, $company] = $this->userWith(['employees.view']);

        $this->actAs($user, $company)->getJson('/api/v1/dashboard')->assertForbidden();
    }

    public function test_rejects_inverted_ranges(): void
    {
        [$user, $company] = $this->userWith(['dashboard.view']);

        $this->actAs($user, $company)->getJson('/api/v1/dashboard?from=2026-10-10&to=2026-10-01')
            ->assertStatus(422)->assertJsonValidationErrors('to');
    }
}
