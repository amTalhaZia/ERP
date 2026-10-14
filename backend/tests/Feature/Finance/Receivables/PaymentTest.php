<?php

namespace Tests\Feature\Finance\Receivables;

use Illuminate\Support\Facades\DB;
use Tests\Feature\Finance\Receivables\Concerns\InteractsWithReceivables;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use InteractsWithReceivables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeFinanceClock();
    }

    public function test_partial_receipt_marks_invoice_partially_paid_and_posts_bank_against_ar(): void
    {
        [$user, $company] = $this->accountant();
        $customer = $this->customer($company);
        $bank = $this->bank($company);
        $invoice = $this->approvedInvoice($user, $company, $customer);

        $payment = $this->recordReceipt($company, $customer, $bank, '100.00', [['type' => 'invoice', 'id' => $invoice['id'], 'amount' => '100.00']])
            ->assertCreated()
            ->assertJsonPath('data.number', 'RCPT-2026-000001')
            ->assertJsonPath('data.type', 'receipt')
            ->assertJsonPath('data.status', 'posted')
            ->assertJsonPath('data.amount', '100.0000')
            ->assertJsonPath('data.allocations.0.document_id', $invoice['id'])
            ->assertJsonPath('data.partner.name', 'Acme Retail')
            ->json('data');

        $this->getJson("/api/v1/invoices/{$invoice['id']}")
            ->assertJsonPath('data.status', 'partially_paid')
            ->assertJsonPath('data.amount_paid', '100.0000')
            ->assertJsonPath('data.balance_due', '107.0000');

        $lines = $this->journalByCode($payment['journal_entry_id']);
        $this->assertSame(['100.0000', '0.0000'], $lines['1010']);
        $this->assertSame(['0.0000', '100.0000'], $lines['1100']);
        $this->assertSame('107.0000', $this->accountBalance($company, '1100'));
        $this->assertLedgerBalanced($company);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payments.receipt_recorded', 'entity_id' => $payment['id']]);
    }

    public function test_invoice_shows_its_payments_and_payments_filter_by_allocated_document(): void
    {
        [$user, $company] = $this->accountant();
        $customer = $this->customer($company);
        $bank = $this->bank($company);
        $invoice = $this->approvedInvoice($user, $company, $customer);
        $otherInvoice = $this->approvedInvoice($user, $company, $customer);

        $payment = $this->recordReceipt($company, $customer, $bank, '50.00', [['type' => 'invoice', 'id' => $invoice['id'], 'amount' => '50.00']])
            ->assertCreated()
            ->assertJsonPath('data.allocations.0.document_number', $invoice['number'])
            ->json('data');
        $this->recordReceipt($company, $customer, $bank, '10.00', [['type' => 'invoice', 'id' => $otherInvoice['id'], 'amount' => '10.00']])->assertCreated();

        $this->getJson("/api/v1/invoices/{$invoice['id']}")
            ->assertOk()
            ->assertJsonCount(1, 'data.payments')
            ->assertJsonPath('data.payments.0.payment_id', $payment['id'])
            ->assertJsonPath('data.payments.0.payment_number', $payment['number'])
            ->assertJsonPath('data.payments.0.payment_date', '2026-10-10')
            ->assertJsonPath('data.payments.0.method', 'bank_transfer')
            ->assertJsonPath('data.payments.0.amount', '50.0000')
            ->assertJsonPath('data.payments.0.status', 'posted');

        $this->getJson("/api/v1/payments?filter[allocatable_type]=invoice&filter[allocatable_id]={$invoice['id']}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $payment['id']);
        $this->getJson("/api/v1/payments?filter[allocatable_type]=bill&filter[allocatable_id]={$invoice['id']}")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_full_payment_marks_invoice_paid(): void
    {
        [$user, $company] = $this->accountant();
        $customer = $this->customer($company);
        $bank = $this->bank($company);
        $invoice = $this->approvedInvoice($user, $company, $customer);

        $this->recordReceipt($company, $customer, $bank, '100', [['type' => 'invoice', 'id' => $invoice['id'], 'amount' => '100']])->assertCreated();
        $this->recordReceipt($company, $customer, $bank, '107', [['type' => 'invoice', 'id' => $invoice['id'], 'amount' => '107']])
            ->assertCreated()->assertJsonPath('data.number', 'RCPT-2026-000002');

        $this->assertDatabaseHas('invoices', ['id' => $invoice['id'], 'status' => 'paid', 'balance_due' => '0.0000', 'amount_paid' => '207.0000']);
        $this->assertSame('0.0000', $this->accountBalance($company, '1100'));
        $this->assertSame('207.0000', $this->accountBalance($company, '1010'));
    }

    public function test_over_allocation_is_rejected_and_nothing_is_persisted(): void
    {
        [$user, $company] = $this->accountant();
        $customer = $this->customer($company);
        $bank = $this->bank($company);
        $invoice = $this->approvedInvoice($user, $company, $customer);
        $entriesBefore = DB::table('journal_entries')->count();

        $this->recordReceipt($company, $customer, $bank, '300', [['type' => 'invoice', 'id' => $invoice['id'], 'amount' => '300']])
            ->assertUnprocessable()->assertJsonValidationErrors(['allocations.0.amount']);

        // Allocations must equal the amount (no unallocated credit).
        $this->recordReceipt($company, $customer, $bank, '150', [['type' => 'invoice', 'id' => $invoice['id'], 'amount' => '100']])
            ->assertUnprocessable()->assertJsonValidationErrors(['allocations']);

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('payment_allocations', 0);
        $this->assertSame($entriesBefore, DB::table('journal_entries')->count());
        $this->assertDatabaseHas('invoices', ['id' => $invoice['id'], 'status' => 'approved', 'balance_due' => '207.0000']);
        $this->assertSame(0, DB::table('document_sequences')->where('company_id', $company->id)->where('type', 'receipt')->count());
    }

    public function test_cannot_allocate_to_draft_invoice_or_another_customers_invoice(): void
    {
        [$user, $company] = $this->accountant();
        $customer = $this->customer($company);
        $otherCustomer = $this->customer($company, name: 'Other Co');
        $bank = $this->bank($company);
        $foreignInvoice = $this->approvedInvoice($user, $company, $otherCustomer);
        $draft = $this->postJson('/api/v1/invoices', $this->invoicePayload($company, $customer))->json('data.id');

        $this->recordReceipt($company, $customer, $bank, '10', [['type' => 'invoice', 'id' => $foreignInvoice['id'], 'amount' => '10']])
            ->assertUnprocessable()->assertJsonValidationErrors(['allocations.0.id']);
        $this->recordReceipt($company, $customer, $bank, '10', [['type' => 'invoice', 'id' => $draft, 'amount' => '10']])
            ->assertUnprocessable()->assertJsonValidationErrors(['allocations.0.id']);
        $this->recordReceipt($company, $customer, $bank, '10', [['type' => 'bill', 'id' => $draft, 'amount' => '10']])
            ->assertUnprocessable()->assertJsonValidationErrors(['allocations.0.type']);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_void_payment_restores_invoice_balance_and_reverses_ledger(): void
    {
        [$user, $company] = $this->accountant();
        $customer = $this->customer($company);
        $bank = $this->bank($company);
        $invoice = $this->approvedInvoice($user, $company, $customer);
        $payment = $this->recordReceipt($company, $customer, $bank, '207', [['type' => 'invoice', 'id' => $invoice['id'], 'amount' => '207']])->json('data');

        $this->postJson("/api/v1/payments/{$payment['id']}/void", ['reason' => 'Bounced transfer'])
            ->assertOk()->assertJsonPath('data.status', 'void');
        $this->postJson("/api/v1/payments/{$payment['id']}/void", ['reason' => 'Again'])->assertStatus(409);

        $this->assertDatabaseHas('invoices', ['id' => $invoice['id'], 'status' => 'approved', 'balance_due' => '207.0000', 'amount_paid' => '0.0000']);
        $this->assertDatabaseHas('journal_entries', ['id' => $payment['journal_entry_id'], 'status' => 'reversed']);
        $this->assertSame('207.0000', $this->accountBalance($company, '1100'));
        $this->assertSame('0.0000', $this->accountBalance($company, '1010'));
        $this->assertLedgerBalanced($company);
    }

    public function test_voiding_an_invoice_with_payments_is_a_conflict(): void
    {
        [$user, $company] = $this->accountant();
        $customer = $this->customer($company);
        $bank = $this->bank($company);
        $invoice = $this->approvedInvoice($user, $company, $customer);
        $this->recordReceipt($company, $customer, $bank, '50', [['type' => 'invoice', 'id' => $invoice['id'], 'amount' => '50']])->assertCreated();

        $this->postJson("/api/v1/invoices/{$invoice['id']}/void", ['reason' => 'Mistake'])
            ->assertStatus(409)->assertJsonPath('code', 'finance.has_payments');

        $this->assertDatabaseHas('invoices', ['id' => $invoice['id'], 'status' => 'partially_paid']);
        $this->assertDatabaseHas('journal_entries', ['id' => $invoice['journal_entry_id'], 'status' => 'posted']);
    }

    public function test_bank_account_and_partner_from_another_company_are_rejected(): void
    {
        [$user, $company] = $this->accountant();
        $other = $this->seededCompany();
        $foreignBank = $this->bank($other);
        $foreignCustomer = $this->customer($other);
        $invoice = $this->approvedInvoice($user, $company, $this->customer($company));

        $this->recordReceipt($company, $foreignCustomer, $foreignBank, '10', [['type' => 'invoice', 'id' => $invoice['id'], 'amount' => '10']])
            ->assertUnprocessable()->assertJsonValidationErrors(['bank_account_id', 'partner_id']);
    }

    public function test_another_companys_payment_is_not_found(): void
    {
        [$user, $company] = $this->accountant();
        [$otherUser, $other] = $this->accountant();
        $otherCustomer = $this->customer($other);
        $otherBank = $this->bank($other);
        $otherInvoice = $this->approvedInvoice($otherUser, $other, $otherCustomer);
        $foreign = $this->recordReceipt($other, $otherCustomer, $otherBank, '10', [['type' => 'invoice', 'id' => $otherInvoice['id'], 'amount' => '10']])
            ->assertCreated()->json('data.id');

        $this->actAs($user, $company);
        $this->getJson("/api/v1/payments/{$foreign}")->assertNotFound();
        $this->postJson("/api/v1/payments/{$foreign}/void", ['reason' => 'Not mine'])->assertNotFound();
        $this->getJson('/api/v1/payments')->assertOk()->assertJsonCount(0, 'data');
        $this->assertDatabaseHas('payments', ['id' => $foreign, 'status' => 'posted']);
    }

    public function test_recording_requires_payments_create(): void
    {
        [$user, $company] = $this->accountant(permissions: ['payments.view', 'invoices.view']);

        $this->actAs($user, $company)->postJson('/api/v1/payments', [])->assertForbidden();
        $this->getJson('/api/v1/payments')->assertOk();
    }

    public function test_empty_payload_is_rejected_with_field_errors(): void
    {
        [$user, $company] = $this->accountant();

        $this->actAs($user, $company)->postJson('/api/v1/payments', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['partner_type', 'partner_id', 'payment_date', 'method', 'bank_account_id', 'amount', 'allocations']);
    }
}
