<?php

namespace Tests\Feature\Finance\Receivables;

use App\Models\Company;
use App\Models\User;
use Tests\Feature\Finance\Receivables\Concerns\InteractsWithReceivables;
use Tests\TestCase;

class ReceivablesReportTest extends TestCase
{
    use InteractsWithReceivables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeFinanceClock(); // today = 2026-10-14
    }

    /**
     * Invoices of 207.00 each due 10, 45 and 100 days ago plus one not yet
     * due; 50.00 received on the 45-day one.
     *
     * @return array{0: User, 1: Company, 2: int, 3: array<string, array<string, mixed>>}
     */
    private function arrangeAging(): array
    {
        [$user, $company] = $this->accountant();
        $customer = $this->customer($company);
        $bank = $this->bank($company);

        $invoices = [
            'due10' => $this->approvedInvoice($user, $company, $customer, ['invoice_date' => '2026-09-01', 'due_date' => '2026-10-04']),
            'due45' => $this->approvedInvoice($user, $company, $customer, ['invoice_date' => '2026-08-01', 'due_date' => '2026-08-30']),
            'due100' => $this->approvedInvoice($user, $company, $customer, ['invoice_date' => '2026-07-01', 'due_date' => '2026-07-06']),
            'current' => $this->approvedInvoice($user, $company, $customer, ['invoice_date' => '2026-10-10', 'due_date' => '2026-10-20']),
        ];

        $this->recordReceipt($company, $customer, $bank, '50', [['type' => 'invoice', 'id' => $invoices['due45']['id'], 'amount' => '50']], '2026-09-15')
            ->assertCreated();

        return [$user, $company, $customer, $invoices];
    }

    public function test_ar_aging_buckets_by_days_past_due(): void
    {
        [$user, $company, $customer] = $this->arrangeAging();
        $other = $this->customer($company, name: 'Zeta Corp');
        $this->approvedInvoice($user, $company, $other, ['invoice_date' => '2026-06-01', 'due_date' => '2026-08-01']); // 74 days

        $response = $this->getJson('/api/v1/finance/reports/ar-aging')->assertOk();

        $response->assertJsonPath('data.as_of', '2026-10-14')
            ->assertJsonCount(2, 'data.rows')
            ->assertJsonPath('data.rows.0.partner_id', $customer)
            ->assertJsonPath('data.rows.0.partner_name', 'Acme Retail')
            ->assertJsonPath('data.rows.0.current', '207.0000')
            ->assertJsonPath('data.rows.0.days_1_30', '207.0000')
            ->assertJsonPath('data.rows.0.days_31_60', '157.0000')
            ->assertJsonPath('data.rows.0.days_61_90', '0.0000')
            ->assertJsonPath('data.rows.0.over_90', '207.0000')
            ->assertJsonPath('data.rows.0.total', '778.0000')
            ->assertJsonPath('data.rows.0.documents', 4)
            ->assertJsonPath('data.rows.1.partner_name', 'Zeta Corp')
            ->assertJsonPath('data.rows.1.days_61_90', '207.0000')
            ->assertJsonPath('data.totals.total', '985.0000')
            ->assertJsonPath('data.totals.days_61_90', '207.0000')
            ->assertJsonPath('data.totals.documents', 5);
    }

    public function test_ar_aging_as_of_an_earlier_date_ignores_later_documents_and_payments(): void
    {
        $this->arrangeAging();

        // As of 2026-09-10: the receipt (09-15) and the 10-10 invoice did not exist yet.
        $this->getJson('/api/v1/finance/reports/ar-aging?as_of=2026-09-10')
            ->assertOk()
            ->assertJsonPath('data.rows.0.current', '207.0000')      // due 10-04
            ->assertJsonPath('data.rows.0.days_1_30', '207.0000')    // due 08-30 → 11 days
            ->assertJsonPath('data.rows.0.days_61_90', '207.0000')   // due 07-06 → 66 days
            ->assertJsonPath('data.rows.0.total', '621.0000');
    }

    public function test_ap_aging_uses_bills(): void
    {
        [$user, $company] = $this->accountant();
        $supplier = $this->supplier($company);
        $id = $this->actAs($user, $company)->postJson('/api/v1/bills', [
            'supplier_id' => $supplier, 'supplier_reference' => 'PM-881', 'bill_date' => '2026-07-20', 'due_date' => '2026-08-05',
            'lines' => [['description' => 'Paper', 'quantity' => '10', 'unit_price' => '12.50']],
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/bills/{$id}/approve")->assertOk();

        $this->getJson('/api/v1/finance/reports/ap-aging')
            ->assertOk()
            ->assertJsonPath('data.rows.0.partner_name', 'Paper Mill Ltd')
            ->assertJsonPath('data.rows.0.days_61_90', '125.0000')
            ->assertJsonPath('data.totals.total', '125.0000');
    }

    public function test_summary_reports_outstanding_overdue_and_days_to_pay(): void
    {
        [$user, $company, $customer, $invoices] = $this->arrangeAging();
        $bank = $this->bank($company);
        $this->recordReceipt($company, $customer, $bank, '207', [['type' => 'invoice', 'id' => $invoices['current']['id'], 'amount' => '207']], '2026-10-13')
            ->assertCreated();

        $this->getJson('/api/v1/finance/reports/receivables-summary')
            ->assertOk()
            ->assertJsonPath('data.receivables.outstanding', '571.0000')
            ->assertJsonPath('data.receivables.open_count', 3)
            ->assertJsonPath('data.receivables.overdue_amount', '571.0000')
            ->assertJsonPath('data.receivables.overdue_count', 3)
            ->assertJsonPath('data.payables.outstanding', '0.0000')
            ->assertJsonPath('data.average_days_to_pay', '3.0');
    }

    public function test_customer_statement_has_opening_running_and_closing_balances(): void
    {
        [, , $customer] = $this->arrangeAging();

        $response = $this->getJson("/api/v1/finance/customer-statements/{$customer}?from=2026-08-01&to=2026-09-30")
            ->assertOk()
            ->assertJsonPath('data.customer.name', 'Acme Retail')
            ->assertJsonPath('data.opening_balance', '207.0000')
            ->assertJsonCount(3, 'data.entries')
            ->assertJsonPath('data.entries.0.type', 'invoice')
            ->assertJsonPath('data.entries.0.balance', '414.0000')
            ->assertJsonPath('data.entries.1.balance', '621.0000')
            ->assertJsonPath('data.entries.2.type', 'receipt')
            ->assertJsonPath('data.entries.2.credit', '50.0000')
            ->assertJsonPath('data.closing_balance', '571.0000');

        $this->assertSame('414.0000', $response->json('data.total_debits'));
    }

    public function test_statement_for_another_companys_customer_is_not_found(): void
    {
        [$user, $company] = $this->accountant();
        $foreignCustomer = $this->customer($this->seededCompany());

        $this->actAs($user, $company)->getJson("/api/v1/finance/customer-statements/{$foreignCustomer}")->assertNotFound();
    }

    public function test_reports_require_financial_report_permission(): void
    {
        [$user, $company] = $this->accountant(permissions: ['invoices.view']);

        $this->actAs($user, $company);
        $this->getJson('/api/v1/finance/reports/ar-aging')->assertForbidden();
        $this->getJson('/api/v1/finance/reports/ap-aging')->assertForbidden();
        $this->getJson('/api/v1/finance/reports/receivables-summary')->assertForbidden();
    }
}
