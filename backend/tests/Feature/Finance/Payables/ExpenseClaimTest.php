<?php

namespace Tests\Feature\Finance\Payables;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Finance\Receivables\Concerns\InteractsWithReceivables;
use Tests\TestCase;

class ExpenseClaimTest extends TestCase
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
    private function claimPayload(Company $company): array
    {
        return [
            'claim_date' => '2026-10-08',
            'notes' => 'Client visit',
            'lines' => [
                ['expense_date' => '2026-10-06', 'category' => 'Travel', 'account_id' => $this->account($company, '6500')->id, 'description' => 'Taxi', 'amount' => '40.00'],
                ['expense_date' => '2026-10-07', 'category' => 'Meals', 'account_id' => $this->account($company, '6500')->id, 'amount' => '60.00', 'tax_rate_id' => $this->standardTax($company)->id],
            ],
        ];
    }

    /**
     * @return array{0: User, 1: User, 2: Company}
     */
    private function employeeAndApprover(): array
    {
        [$approver, $company] = $this->accountant();
        $employee = User::factory()->inCompany($company, ['expenses.submit'])->create();

        return [$employee, $approver, $company];
    }

    public function test_submit_approve_and_pay_flow(): void
    {
        [$employee, $approver, $company] = $this->employeeAndApprover();

        $claim = $this->actAs($employee, $company)->postJson('/api/v1/expense-claims', $this->claimPayload($company))
            ->assertCreated()
            ->assertJsonPath('data.number', 'EXP-2026-000001')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.user_id', $employee->id)
            ->assertJsonPath('data.subtotal', '100.0000')
            ->assertJsonPath('data.tax_total', '9.0000')
            ->assertJsonPath('data.total', '109.0000')
            ->json('data');

        $this->postJson("/api/v1/expense-claims/{$claim['id']}/submit")->assertOk()->assertJsonPath('data.status', 'submitted');

        $note = DB::table('notifications')->where('notifiable_id', $approver->id)->first();
        $this->assertSame('expense_claim.submitted', json_decode($note->data, true)['type']);

        $approved = $this->actAs($approver, $company)->postJson("/api/v1/expense-claims/{$claim['id']}/approve")
            ->assertOk()->assertJsonPath('data.status', 'approved')->assertJsonPath('data.approver_id', $approver->id)->json('data');

        $lines = $this->journalByCode($approved['journal_entry_id']);
        $this->assertSame(['100.0000', '0.0000'], $lines['6500']);
        $this->assertSame(['9.0000', '0.0000'], $lines['2100']);
        $this->assertSame(['0.0000', '109.0000'], $lines['2300']);
        $this->assertDatabaseHas('journal_lines', ['journal_entry_id' => $approved['journal_entry_id'], 'partner_type' => 'employee', 'partner_id' => $employee->id]);

        $decision = DB::table('notifications')->where('notifiable_id', $employee->id)->first();
        $this->assertSame('expense_claim.approved', json_decode($decision->data, true)['type']);
        $this->assertSame($company->id, json_decode($decision->data, true)['company_id']);

        $bank = $this->bank($company);
        $payment = $this->postJson('/api/v1/payments', [
            'partner_type' => 'employee', 'partner_id' => $employee->id, 'payment_date' => '2026-10-13', 'method' => 'bank_transfer',
            'bank_account_id' => $bank->id, 'amount' => '109', 'allocations' => [['type' => 'expense_claim', 'id' => $claim['id'], 'amount' => '109']],
        ])->assertCreated()->assertJsonPath('data.type', 'payment')->json('data');

        $paymentLines = $this->journalByCode($payment['journal_entry_id']);
        $this->assertSame(['109.0000', '0.0000'], $paymentLines['2300']);
        $this->assertSame(['0.0000', '109.0000'], $paymentLines['1010']);
        $this->assertDatabaseHas('expense_claims', ['id' => $claim['id'], 'status' => 'paid', 'balance_due' => '0.0000']);
        $this->assertSame('0.0000', $this->accountBalance($company, '2300'));
        $this->assertLedgerBalanced($company);
    }

    public function test_approving_own_claim_is_forbidden(): void
    {
        [$approver, $company] = $this->accountant();
        $id = $this->actAs($approver, $company)->postJson('/api/v1/expense-claims', $this->claimPayload($company))->json('data.id');
        $this->postJson("/api/v1/expense-claims/{$id}/submit")->assertOk();

        $this->postJson("/api/v1/expense-claims/{$id}/approve")->assertForbidden();
        $this->postJson("/api/v1/expense-claims/{$id}/reject", ['reason' => 'nope'])->assertForbidden();

        $this->assertDatabaseHas('expense_claims', ['id' => $id, 'status' => 'submitted', 'journal_entry_id' => null]);
    }

    public function test_reject_requires_reason_and_notifies_submitter(): void
    {
        [$employee, $approver, $company] = $this->employeeAndApprover();
        $id = $this->actAs($employee, $company)->postJson('/api/v1/expense-claims', $this->claimPayload($company))->json('data.id');
        $this->postJson("/api/v1/expense-claims/{$id}/submit")->assertOk();

        $this->actAs($approver, $company)->postJson("/api/v1/expense-claims/{$id}/reject", [])
            ->assertUnprocessable()->assertJsonValidationErrors(['reason']);
        $this->postJson("/api/v1/expense-claims/{$id}/reject", ['reason' => 'Missing receipts'])
            ->assertOk()->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.rejection_reason', 'Missing receipts');

        $note = DB::table('notifications')->where('notifiable_id', $employee->id)->first();
        $this->assertSame('expense_claim.rejected', json_decode($note->data, true)['type']);
        $this->assertDatabaseCount('journal_entries', 0);

        // A rejected claim can be edited (back to draft) and resubmitted.
        $this->actAs($employee, $company)->putJson("/api/v1/expense-claims/{$id}", ['notes' => 'Receipts attached'])
            ->assertOk()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.rejection_reason', null);
    }

    public function test_submitter_only_sees_and_edits_own_claims(): void
    {
        [$employee, $approver, $company] = $this->employeeAndApprover();
        $colleague = User::factory()->inCompany($company, ['expenses.submit'])->create();

        $own = $this->actAs($employee, $company)->postJson('/api/v1/expense-claims', $this->claimPayload($company))->json('data.id');
        $theirs = $this->actAs($colleague, $company)->postJson('/api/v1/expense-claims', $this->claimPayload($company))->json('data.id');

        $this->actAs($employee, $company)->getJson('/api/v1/expense-claims')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own);
        $this->getJson("/api/v1/expense-claims/{$theirs}")->assertForbidden();
        $this->putJson("/api/v1/expense-claims/{$theirs}", ['notes' => 'hijack'])->assertForbidden();
        $this->deleteJson("/api/v1/expense-claims/{$theirs}")->assertForbidden();
        $this->postJson("/api/v1/expense-claims/{$theirs}/submit")->assertForbidden();

        $this->actAs($approver, $company)->getJson('/api/v1/expense-claims')->assertOk()->assertJsonCount(2, 'data');
        $this->assertDatabaseHas('expense_claims', ['id' => $theirs, 'notes' => 'Client visit']);
    }

    public function test_approved_claim_cannot_be_edited(): void
    {
        [$employee, $approver, $company] = $this->employeeAndApprover();
        $id = $this->actAs($employee, $company)->postJson('/api/v1/expense-claims', $this->claimPayload($company))->json('data.id');
        $this->postJson("/api/v1/expense-claims/{$id}/submit")->assertOk();
        $this->actAs($approver, $company)->postJson("/api/v1/expense-claims/{$id}/approve")->assertOk();

        $this->actAs($employee, $company)->putJson("/api/v1/expense-claims/{$id}", ['notes' => 'x'])->assertStatus(409);
        $this->deleteJson("/api/v1/expense-claims/{$id}")->assertStatus(409);
    }

    public function test_receipt_upload_and_download(): void
    {
        Storage::fake('local');
        [$employee, $approver, $company] = $this->employeeAndApprover();
        $claim = $this->actAs($employee, $company)->postJson('/api/v1/expense-claims', $this->claimPayload($company))->json('data');
        $lineId = $claim['lines'][0]['id'];

        $this->postJson("/api/v1/expense-claims/{$claim['id']}/lines/{$lineId}/receipt", ['receipt' => UploadedFile::fake()->create('notes.exe', 10)])
            ->assertUnprocessable()->assertJsonValidationErrors(['receipt']);

        $this->post("/api/v1/expense-claims/{$claim['id']}/lines/{$lineId}/receipt", ['receipt' => UploadedFile::fake()->image('taxi.jpg')], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.lines.0.has_receipt', true)->assertJsonPath('data.lines.0.receipt_name', 'taxi.jpg');

        $path = DB::table('expense_claim_lines')->where('id', $lineId)->value('receipt_path');
        Storage::disk('local')->assertExists($path);
        $this->assertStringStartsWith("expense-receipts/{$company->id}/{$claim['id']}/", $path);

        $this->get("/api/v1/expense-claims/{$claim['id']}/lines/{$lineId}/receipt")->assertOk()->assertDownload('taxi.jpg');
        $this->actAs($approver, $company)->get("/api/v1/expense-claims/{$claim['id']}/lines/{$lineId}/receipt")->assertOk();

        $outsider = User::factory()->inCompany($company, ['expenses.submit'])->create();
        $this->actAs($outsider, $company)->getJson("/api/v1/expense-claims/{$claim['id']}/lines/{$lineId}/receipt")->assertForbidden();
    }

    public function test_updating_lines_by_id_keeps_their_receipts_and_deletes_omitted_lines(): void
    {
        Storage::fake('local');
        [$employee, , $company] = $this->employeeAndApprover();
        $claim = $this->actAs($employee, $company)->postJson('/api/v1/expense-claims', $this->claimPayload($company))->json('data');
        [$taxiId, $mealsId] = array_column($claim['lines'], 'id');

        foreach ([$taxiId => 'taxi.jpg', $mealsId => 'meals.jpg'] as $lineId => $name) {
            $this->post("/api/v1/expense-claims/{$claim['id']}/lines/{$lineId}/receipt", ['receipt' => UploadedFile::fake()->image($name)], ['Accept' => 'application/json'])->assertOk();
        }
        $taxiReceipt = DB::table('expense_claim_lines')->where('id', $taxiId)->value('receipt_path');
        $mealsReceipt = DB::table('expense_claim_lines')->where('id', $mealsId)->value('receipt_path');
        $account = $this->account($company, '6500')->id;

        $this->putJson("/api/v1/expense-claims/{$claim['id']}", [
            'lines' => [
                ['id' => $taxiId, 'expense_date' => '2026-10-06', 'category' => 'Travel', 'account_id' => $account, 'description' => 'Taxi (corrected)', 'amount' => '45.00'],
                ['expense_date' => '2026-10-07', 'category' => 'Parking', 'account_id' => $account, 'amount' => '5.00'],
            ],
        ])->assertOk()
            ->assertJsonCount(2, 'data.lines')
            ->assertJsonPath('data.lines.0.id', $taxiId)
            ->assertJsonPath('data.lines.0.has_receipt', true)
            ->assertJsonPath('data.lines.1.has_receipt', false)
            ->assertJsonPath('data.total', '50.0000');

        $this->assertDatabaseHas('expense_claim_lines', ['id' => $taxiId, 'receipt_path' => $taxiReceipt, 'amount' => '45.0000', 'line_no' => 1]);
        $this->assertDatabaseMissing('expense_claim_lines', ['id' => $mealsId]);
        Storage::disk('local')->assertExists($taxiReceipt);
        Storage::disk('local')->assertMissing($mealsReceipt);

        $other = $this->postJson('/api/v1/expense-claims', $this->claimPayload($company))->json('data');
        $this->putJson("/api/v1/expense-claims/{$claim['id']}", [
            'lines' => [['id' => $other['lines'][0]['id'], 'expense_date' => '2026-10-06', 'category' => 'Travel', 'account_id' => $account, 'amount' => '1.00']],
        ])->assertUnprocessable()->assertJsonValidationErrors(['lines.0.id']);
        $this->assertDatabaseHas('expense_claim_lines', ['id' => $taxiId, 'amount' => '45.0000']);
    }

    public function test_line_of_another_claim_is_not_found_via_scoped_binding(): void
    {
        [$employee, , $company] = $this->employeeAndApprover();
        $first = $this->actAs($employee, $company)->postJson('/api/v1/expense-claims', $this->claimPayload($company))->json('data');
        $second = $this->postJson('/api/v1/expense-claims', $this->claimPayload($company))->json('data');

        $this->getJson("/api/v1/expense-claims/{$first['id']}/lines/{$second['lines'][0]['id']}/receipt")->assertNotFound();
    }

    public function test_another_companys_claim_is_not_found(): void
    {
        [$user, $company] = $this->accountant();
        [$otherUser, $other] = $this->accountant();
        $foreign = $this->actAs($otherUser, $other)->postJson('/api/v1/expense-claims', $this->claimPayload($other))->json('data.id');

        $this->actAs($user, $company);
        $this->getJson("/api/v1/expense-claims/{$foreign}")->assertNotFound();
        $this->putJson("/api/v1/expense-claims/{$foreign}", ['notes' => 'x'])->assertNotFound();
        $this->postJson("/api/v1/expense-claims/{$foreign}/approve")->assertNotFound();
    }

    public function test_invalid_accounts_and_empty_payload_are_rejected(): void
    {
        [$user, $company] = $this->accountant();
        $other = $this->seededCompany();
        $payload = $this->claimPayload($company);
        $payload['lines'][0]['account_id'] = $this->account($other, '6500')->id;
        $payload['lines'][1]['account_id'] = $this->account($company, '1010')->id;

        $this->actAs($user, $company)->postJson('/api/v1/expense-claims', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors(['lines.0.account_id', 'lines.1.account_id']);
        $this->postJson('/api/v1/expense-claims', [])
            ->assertUnprocessable()->assertJsonValidationErrors(['claim_date', 'lines']);
        $this->assertDatabaseCount('expense_claims', 0);
    }

    public function test_creating_requires_expenses_submit(): void
    {
        [$user, $company] = $this->accountant(permissions: ['expenses.view']);

        $this->actAs($user, $company)->postJson('/api/v1/expense-claims', [])->assertForbidden();
    }
}
