<?php

namespace Tests\Feature\Finance\Receivables;

use App\Models\Finance\Invoice;
use App\Models\Finance\TaxRate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Finance\Receivables\Concerns\InteractsWithReceivables;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use InteractsWithReceivables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeFinanceClock();
    }

    public function test_creates_a_draft_with_exact_server_side_totals_and_default_due_date(): void
    {
        [$user, $company] = $this->accountant();
        $customer = $this->customer($company, termsDays: 14);

        $response = $this->actAs($user, $company)->postJson('/api/v1/invoices', $this->invoicePayload($company, $customer))
            ->assertCreated()
            ->assertJsonPath('data.number', 'INV-2026-000001')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.due_date', '2026-10-15')
            ->assertJsonPath('data.subtotal', '180.0000')
            ->assertJsonPath('data.discount_total', '20.0000')
            ->assertJsonPath('data.tax_total', '27.0000')
            ->assertJsonPath('data.total', '207.0000')
            ->assertJsonPath('data.balance_due', '207.0000')
            ->assertJsonPath('data.customer.name', 'Acme Retail')
            ->assertJsonPath('data.lines.0.account.code', '4000')
            ->assertJsonPath('data.lines.0.tax_rate', '15.0000')
            ->assertJsonPath('data.lines.0.line_total', '207.0000');

        $this->assertDatabaseHas('invoices', ['id' => $response->json('data.id'), 'company_id' => $company->id, 'created_by' => $user->id]);
    }

    public function test_line_rounding_is_half_up_per_line(): void
    {
        [$user, $company] = $this->accountant();
        $customer = $this->customer($company);
        $tax = $this->standardTax($company)->id;

        // 3 × 3.333 = 9.999 → 10.00; tax 1.50. 1 × 0.05 tax 15% = 0.0075 → 0.01.
        $this->actAs($user, $company)->postJson('/api/v1/invoices', $this->invoicePayload($company, $customer, [
            ['description' => 'A', 'quantity' => '3', 'unit_price' => '3.333', 'tax_rate_id' => $tax],
            ['description' => 'B', 'quantity' => '1', 'unit_price' => '0.05', 'tax_rate_id' => $tax],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.subtotal', '10.0500')
            ->assertJsonPath('data.tax_total', '1.5100')
            ->assertJsonPath('data.total', '11.5600');
    }

    public function test_lines_keep_input_order_when_only_some_have_a_product(): void
    {
        [$user, $company] = $this->accountant();
        $customer = $this->customer($company);

        $this->actAs($user, $company)->postJson('/api/v1/invoices', $this->invoicePayload($company, $customer, [
            ['description' => 'First (no product)', 'quantity' => '1', 'unit_price' => '10'],
            ['description' => 'Second (product)', 'product_id' => $this->product($company), 'quantity' => '1', 'unit_price' => '20'],
            ['description' => 'Third', 'quantity' => '1', 'unit_price' => '30'],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.lines.0.description', 'First (no product)')
            ->assertJsonPath('data.lines.0.line_no', 1)
            ->assertJsonPath('data.lines.1.description', 'Second (product)')
            ->assertJsonPath('data.lines.2.description', 'Third');
    }

    public function test_empty_payload_is_rejected_with_field_errors(): void
    {
        [$user, $company] = $this->accountant();

        $this->actAs($user, $company)->postJson('/api/v1/invoices', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id', 'invoice_date', 'lines']);
    }

    public function test_references_from_another_company_are_rejected(): void
    {
        [$user, $company] = $this->accountant();
        $other = $this->seededCompany();
        $customer = $this->customer($company);
        $foreignCustomer = $this->customer($other);
        $foreignTax = $this->standardTax($other)->id;
        $foreignAccount = $this->account($other, '4000')->id;

        $payload = $this->invoicePayload($company, $foreignCustomer, [[
            'description' => 'X', 'quantity' => '1', 'unit_price' => '1', 'tax_rate_id' => $foreignTax, 'account_id' => $foreignAccount,
        ]]);

        $this->actAs($user, $company)->postJson('/api/v1/invoices', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id', 'lines.0.tax_rate_id', 'lines.0.account_id']);

        $payload['customer_id'] = $customer;
        $payload['lines'][0]['tax_rate_id'] = null;
        $payload['lines'][0]['account_id'] = $this->account($company, '6300')->id;

        $this->postJson('/api/v1/invoices', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.account_id']);

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_another_companys_invoice_is_not_found(): void
    {
        [$user, $company] = $this->accountant();
        [$otherUser, $other] = $this->accountant();
        $foreign = $this->approvedInvoice($otherUser, $other, $this->customer($other));

        $this->actAs($user, $company);
        $this->getJson("/api/v1/invoices/{$foreign['id']}")->assertNotFound();
        $this->putJson("/api/v1/invoices/{$foreign['id']}", ['notes' => 'x'])->assertNotFound();
        $this->deleteJson("/api/v1/invoices/{$foreign['id']}")->assertNotFound();
        $this->postJson("/api/v1/invoices/{$foreign['id']}/approve")->assertNotFound();
        $this->getJson("/api/v1/invoices/{$foreign['id']}/pdf")->assertNotFound();
        $this->getJson('/api/v1/invoices')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_approving_without_permission_is_forbidden_and_changes_nothing(): void
    {
        [$clerk, $company] = $this->accountant(permissions: ['invoices.view', 'invoices.create', 'invoices.update']);
        $id = $this->actAs($clerk, $company)->postJson('/api/v1/invoices', $this->invoicePayload($company, $this->customer($company)))
            ->assertCreated()->json('data.id');

        $this->postJson("/api/v1/invoices/{$id}/approve")->assertForbidden();

        $this->assertDatabaseHas('invoices', ['id' => $id, 'status' => 'draft', 'journal_entry_id' => null]);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_submit_notifies_approvers_and_approval_posts_a_balanced_journal(): void
    {
        [$clerk, $company] = $this->accountant(permissions: ['invoices.view', 'invoices.create']);
        [$approver] = $this->accountant($company);
        $customer = $this->customer($company);

        $id = $this->actAs($clerk, $company)->postJson('/api/v1/invoices', $this->invoicePayload($company, $customer))
            ->assertCreated()->json('data.id');
        $this->postJson("/api/v1/invoices/{$id}/submit")->assertOk()->assertJsonPath('data.status', 'pending_approval');

        $notification = DB::table('notifications')->where('notifiable_id', $approver->id)->first();
        $this->assertNotNull($notification);
        $this->assertSame('invoice.submitted', json_decode($notification->data, true)['type']);
        $this->assertSame($company->id, json_decode($notification->data, true)['company_id']);
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $clerk->id)->count());

        $data = $this->actAs($approver, $company)->postJson("/api/v1/invoices/{$id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by', $approver->id)
            ->json('data');

        $lines = $this->journalByCode($data['journal_entry_id']);
        $this->assertSame(['207.0000', '0.0000'], $lines['1100']);
        $this->assertSame(['0.0000', '180.0000'], $lines['4000']);
        $this->assertSame(['0.0000', '27.0000'], $lines['2100']);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $data['journal_entry_id'], 'partner_type' => 'customer', 'partner_id' => $customer, 'debit' => '207.0000',
        ]);
        $this->assertDatabaseHas('journal_entries', ['id' => $data['journal_entry_id'], 'status' => 'posted', 'source_type' => 'invoice', 'source_id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'invoices.approved', 'entity_id' => $id, 'actor_id' => $approver->id]);
        $this->assertLedgerBalanced($company);

        $this->actAs($approver, $company)->getJson('/api/v1/finance/reports/trial-balance?as_of=2026-10-31')
            ->assertOk()->assertJsonPath('data.totals.balanced', true);
    }

    public function test_posted_invoice_is_immutable(): void
    {
        [$user, $company] = $this->accountant();
        $invoice = $this->approvedInvoice($user, $company, $this->customer($company));

        $this->putJson("/api/v1/invoices/{$invoice['id']}", ['notes' => 'changed', 'lines' => [['description' => 'X', 'quantity' => 1, 'unit_price' => 1]]])
            ->assertStatus(409)->assertJsonPath('code', 'conflict');
        $this->deleteJson("/api/v1/invoices/{$invoice['id']}")->assertStatus(409);
        $this->postJson("/api/v1/invoices/{$invoice['id']}/approve")->assertStatus(409);

        $this->assertDatabaseHas('invoices', ['id' => $invoice['id'], 'notes' => null, 'total' => '207.0000', 'status' => 'approved']);
        $this->assertSame(1, DB::table('invoice_lines')->where('invoice_id', $invoice['id'])->count());
    }

    public function test_draft_can_be_updated_and_deleted(): void
    {
        [$user, $company] = $this->accountant();
        $customer = $this->customer($company);
        $id = $this->actAs($user, $company)->postJson('/api/v1/invoices', $this->invoicePayload($company, $customer))->json('data.id');

        $this->putJson("/api/v1/invoices/{$id}", ['lines' => [['description' => 'New', 'quantity' => '4', 'unit_price' => '25']]])
            ->assertOk()
            ->assertJsonPath('data.total', '100.0000')
            ->assertJsonCount(1, 'data.lines');

        $this->deleteJson("/api/v1/invoices/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('invoices', ['id' => $id]);
        $this->assertDatabaseCount('invoice_lines', 0);
    }

    public function test_voiding_an_unpaid_posted_invoice_reverses_the_ledger(): void
    {
        [$user, $company] = $this->accountant();
        $invoice = $this->approvedInvoice($user, $company, $this->customer($company));

        $this->postJson("/api/v1/invoices/{$invoice['id']}/void", [])->assertUnprocessable()->assertJsonValidationErrors(['reason']);

        $this->postJson("/api/v1/invoices/{$invoice['id']}/void", ['reason' => 'Issued in error'])
            ->assertOk()
            ->assertJsonPath('data.status', 'void')
            ->assertJsonPath('data.void_reason', 'Issued in error');

        $this->assertDatabaseHas('journal_entries', ['id' => $invoice['journal_entry_id'], 'status' => 'reversed']);
        $this->assertSame('0.0000', $this->accountBalance($company, '1100'));
        $this->assertSame('0.0000', $this->accountBalance($company, '4000'));
        $this->assertLedgerBalanced($company);
        $this->assertDatabaseHas('audit_logs', ['action' => 'invoices.voided', 'entity_id' => $invoice['id']]);
    }

    public function test_overdue_flag_and_filter(): void
    {
        [$user, $company] = $this->accountant();
        $customer = $this->customer($company);
        $overdue = $this->approvedInvoice($user, $company, $customer, ['invoice_date' => '2026-08-01', 'due_date' => '2026-09-01']);
        $this->approvedInvoice($user, $company, $customer, ['invoice_date' => '2026-10-10']);
        $this->postJson('/api/v1/invoices', $this->invoicePayload($company, $customer, date: '2026-01-01'))->assertCreated();

        $this->assertTrue($overdue['is_overdue']);

        $this->getJson('/api/v1/invoices?overdue=true')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $overdue['id'])->assertJsonPath('data.0.is_overdue', true);
        $this->getJson('/api/v1/invoices?filter[status]=overdue')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/invoices?filter[status]=approved')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/v1/invoices?filter[customer_id]={$customer}&from=2026-10-01&to=2026-10-31")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/invoices?search=000001')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_tax_rate_with_its_own_account_posts_tax_there(): void
    {
        [$user, $company] = $this->accountant();
        $vatAccount = $this->account($company, '2300');
        $rate = TaxRate::factory()->create(['company_id' => $company->id, 'code' => 'VAT5', 'rate' => '5', 'account_id' => $vatAccount->id]);

        $invoice = $this->approvedInvoice($user, $company, $this->customer($company), [
            'lines' => [['description' => 'Item', 'quantity' => '1', 'unit_price' => '100', 'tax_rate_id' => $rate->id]],
        ]);

        $lines = $this->journalByCode($invoice['journal_entry_id']);
        $this->assertSame(['0.0000', '5.0000'], $lines['2300']);
        $this->assertArrayNotHasKey('2100', $lines);
    }

    public function test_pdf_download_is_a_pdf_and_is_audited(): void
    {
        [$user, $company] = $this->accountant();
        $invoice = $this->approvedInvoice($user, $company, $this->customer($company));

        $response = $this->get("/api/v1/invoices/{$invoice['id']}/pdf")->assertOk();

        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('INV-2026-000001.pdf', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
        $this->assertDatabaseHas('audit_logs', ['action' => 'invoices.exported', 'entity_id' => $invoice['id'], 'actor_id' => $user->id]);
    }

    public function test_pdf_requires_view_permission(): void
    {
        [$user, $company] = $this->accountant();
        $invoice = $this->approvedInvoice($user, $company, $this->customer($company));
        $outsider = User::factory()->inCompany($company, ['bills.view'])->create();

        $this->actAs($outsider, $company)->get("/api/v1/invoices/{$invoice['id']}/pdf")->assertForbidden();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'invoices.exported']);
        $this->assertSame(1, Invoice::forCompany($company->id)->count());
    }
}
