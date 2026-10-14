<?php

namespace Tests\Feature\Finance\Payables;

use App\Models\Company;
use Tests\Feature\Finance\Receivables\Concerns\InteractsWithReceivables;
use Tests\TestCase;

class BillTest extends TestCase
{
    use InteractsWithReceivables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeFinanceClock();
    }

    /**
     * @return array<string, mixed>
     */
    private function billPayload(Company $company, int $supplierId, string $reference = 'PM-1001'): array
    {
        return [
            'supplier_id' => $supplierId,
            'supplier_reference' => $reference,
            'bill_date' => '2026-10-05',
            'lines' => [
                ['description' => 'Printer paper', 'quantity' => '10', 'unit_price' => '20.00', 'tax_rate_id' => $this->standardTax($company)->id],
                ['description' => 'Toner (stock item)', 'product_id' => $this->product($company), 'quantity' => '2', 'unit_price' => '50.00'],
            ],
        ];
    }

    public function test_creates_a_draft_bill_with_default_accounts_and_totals(): void
    {
        [$user, $company] = $this->accountant();
        $supplier = $this->supplier($company);

        $this->actAs($user, $company)->postJson('/api/v1/bills', $this->billPayload($company, $supplier))
            ->assertCreated()
            ->assertJsonPath('data.number', 'BILL-2026-000001')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.due_date', '2026-11-04')
            ->assertJsonPath('data.supplier.name', 'Paper Mill Ltd')
            ->assertJsonPath('data.subtotal', '300.0000')
            ->assertJsonPath('data.tax_total', '30.0000')
            ->assertJsonPath('data.total', '330.0000')
            ->assertJsonPath('data.lines.0.account.code', '7900')
            ->assertJsonPath('data.lines.1.account.code', '1200');
    }

    public function test_approval_debits_expense_inventory_and_input_tax_and_credits_ap(): void
    {
        [$user, $company] = $this->accountant();
        $supplier = $this->supplier($company);
        $id = $this->actAs($user, $company)->postJson('/api/v1/bills', $this->billPayload($company, $supplier))->json('data.id');

        $data = $this->postJson("/api/v1/bills/{$id}/approve")->assertOk()->assertJsonPath('data.status', 'approved')->json('data');

        $lines = $this->journalByCode($data['journal_entry_id']);
        $this->assertSame(['200.0000', '0.0000'], $lines['7900']);
        $this->assertSame(['100.0000', '0.0000'], $lines['1200']);
        $this->assertSame(['30.0000', '0.0000'], $lines['2100']);
        $this->assertSame(['0.0000', '330.0000'], $lines['2000']);
        $this->assertDatabaseHas('journal_lines', ['journal_entry_id' => $data['journal_entry_id'], 'partner_type' => 'supplier', 'partner_id' => $supplier]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'bills.approved', 'entity_id' => $id]);
        $this->assertLedgerBalanced($company);
    }

    public function test_supplier_payment_settles_the_bill(): void
    {
        [$user, $company] = $this->accountant();
        $supplier = $this->supplier($company);
        $bank = $this->bank($company);
        $id = $this->actAs($user, $company)->postJson('/api/v1/bills', $this->billPayload($company, $supplier))->json('data.id');
        $this->postJson("/api/v1/bills/{$id}/approve")->assertOk();

        $payment = $this->postJson('/api/v1/payments', [
            'partner_type' => 'supplier', 'partner_id' => $supplier, 'payment_date' => '2026-10-12', 'method' => 'cheque',
            'bank_account_id' => $bank->id, 'amount' => '330', 'allocations' => [['type' => 'bill', 'id' => $id, 'amount' => '330']],
        ])->assertCreated()->assertJsonPath('data.number', 'PAY-2026-000001')->assertJsonPath('data.type', 'payment')->json('data');

        $lines = $this->journalByCode($payment['journal_entry_id']);
        $this->assertSame(['330.0000', '0.0000'], $lines['2000']);
        $this->assertSame(['0.0000', '330.0000'], $lines['1010']);
        $this->assertDatabaseHas('bills', ['id' => $id, 'status' => 'paid', 'balance_due' => '0.0000']);
        $this->assertSame('0.0000', $this->accountBalance($company, '2000'));
    }

    public function test_supplier_reference_is_unique_per_supplier(): void
    {
        [$user, $company] = $this->accountant();
        $supplier = $this->supplier($company);
        $otherSupplier = $this->supplier($company, 'Ink Co');

        $this->actAs($user, $company)->postJson('/api/v1/bills', $this->billPayload($company, $supplier))->assertCreated();
        $this->postJson('/api/v1/bills', $this->billPayload($company, $supplier))
            ->assertUnprocessable()->assertJsonValidationErrors(['supplier_reference']);
        $this->postJson('/api/v1/bills', $this->billPayload($company, $otherSupplier))->assertCreated();
    }

    public function test_another_companys_bill_is_not_found_and_foreign_supplier_rejected(): void
    {
        [$user, $company] = $this->accountant();
        [$otherUser, $other] = $this->accountant();
        $foreignSupplier = $this->supplier($other);
        $foreign = $this->actAs($otherUser, $other)->postJson('/api/v1/bills', $this->billPayload($other, $foreignSupplier))->json('data.id');

        $this->actAs($user, $company);
        $this->getJson("/api/v1/bills/{$foreign}")->assertNotFound();
        $this->putJson("/api/v1/bills/{$foreign}", ['notes' => 'x'])->assertNotFound();
        $this->postJson("/api/v1/bills/{$foreign}/approve")->assertNotFound();
        $this->deleteJson("/api/v1/bills/{$foreign}")->assertNotFound();

        $payload = $this->billPayload($company, $foreignSupplier, 'X-1');
        $payload['lines'][0]['tax_rate_id'] = $this->standardTax($other)->id;
        $payload['lines'][0]['account_id'] = $this->account($other, '6300')->id;

        $this->postJson('/api/v1/bills', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors(['supplier_id', 'lines.0.tax_rate_id', 'lines.0.account_id']);
        $this->assertDatabaseCount('bills', 1);
    }

    public function test_approve_requires_bills_approve_and_posted_bill_is_immutable(): void
    {
        [$clerk, $company] = $this->accountant(permissions: ['bills.view', 'bills.create']);
        [$approver] = $this->accountant($company);
        $supplier = $this->supplier($company);
        $id = $this->actAs($clerk, $company)->postJson('/api/v1/bills', $this->billPayload($company, $supplier))->json('data.id');

        $this->postJson("/api/v1/bills/{$id}/approve")->assertForbidden();
        $this->assertDatabaseHas('bills', ['id' => $id, 'status' => 'draft']);

        $this->actAs($approver, $company)->postJson("/api/v1/bills/{$id}/approve")->assertOk();
        $this->actAs($clerk, $company)->putJson("/api/v1/bills/{$id}", ['notes' => 'late edit'])->assertStatus(409);
        $this->deleteJson("/api/v1/bills/{$id}")->assertStatus(409);
        $this->assertDatabaseHas('bills', ['id' => $id, 'notes' => null, 'status' => 'approved']);
    }

    public function test_void_bill_reverses_the_ledger(): void
    {
        [$user, $company] = $this->accountant();
        $id = $this->actAs($user, $company)->postJson('/api/v1/bills', $this->billPayload($company, $this->supplier($company)))->json('data.id');
        $this->postJson("/api/v1/bills/{$id}/approve")->assertOk();

        $this->postJson("/api/v1/bills/{$id}/void", ['reason' => 'Duplicate'])->assertOk()->assertJsonPath('data.status', 'void');

        $this->assertSame('0.0000', $this->accountBalance($company, '2000'));
        $this->assertLedgerBalanced($company);
    }

    public function test_empty_payload_is_rejected_with_field_errors(): void
    {
        [$user, $company] = $this->accountant();

        $this->actAs($user, $company)->postJson('/api/v1/bills', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['supplier_id', 'supplier_reference', 'bill_date', 'lines']);
    }
}
