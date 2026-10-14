<?php

namespace Tests\Feature\Finance;

use App\Models\Finance\BankAccount;
use App\Models\Finance\TaxRate;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Finance\Concerns\InteractsWithLedger;
use Tests\TestCase;

class TaxAndBankAccountTest extends TestCase
{
    use InteractsWithLedger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeFinanceClock();
    }

    public function test_tax_rates_crud_with_permissions(): void
    {
        [$manager, $company] = $this->financeUser(['tax.manage']);
        $payable = $this->account($company, '2100');

        $id = $this->actAs($manager, $company)->postJson('/api/v1/tax-rates', [
            'name' => 'Reduced', 'code' => 'RED', 'rate' => '7.5', 'type' => 'sales', 'account_id' => $payable->id,
        ])->assertCreated()
            ->assertJsonPath('data.rate', '7.5000')
            ->assertJsonPath('data.account.code', '2100')
            ->json('data.id');

        $this->putJson("/api/v1/tax-rates/{$id}", ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
        $this->postJson('/api/v1/tax-rates', ['name' => 'Dup', 'code' => 'STD', 'rate' => '1', 'type' => 'both'])
            ->assertUnprocessable()->assertJsonValidationErrors(['code']);
        $this->deleteJson("/api/v1/tax-rates/{$id}")->assertNoContent();

        [$reader] = $this->financeUser(['accounts.view'], $company);
        $this->actAs($reader, $company)->getJson('/api/v1/tax-rates')->assertOk()->assertJsonCount(2, 'data');
        $this->postJson('/api/v1/tax-rates', ['name' => 'X', 'code' => 'X', 'rate' => '1', 'type' => 'both'])->assertForbidden();
    }

    public function test_tax_rate_cannot_point_at_another_companys_account_or_be_read_cross_tenant(): void
    {
        [$manager, $company] = $this->financeUser(['tax.manage']);
        $other = $this->seededCompany();
        $foreignRate = TaxRate::forCompany($other->id)->firstOrFail();

        $this->actAs($manager, $company)->postJson('/api/v1/tax-rates', [
            'name' => 'Bad', 'code' => 'BAD', 'rate' => '5', 'type' => 'both', 'account_id' => $this->account($other, '2100')->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['account_id']);

        $this->getJson("/api/v1/tax-rates/{$foreignRate->id}")->assertNotFound();
        $this->deleteJson("/api/v1/tax-rates/{$foreignRate->id}")->assertNotFound();
    }

    public function test_bank_account_number_is_encrypted_at_rest_and_masked_in_output(): void
    {
        [$manager, $company] = $this->financeUser(['bank.manage']);
        $bankGl = $this->account($company, '1010');

        $id = $this->actAs($manager, $company)->postJson('/api/v1/bank-accounts', [
            'account_id' => $bankGl->id, 'name' => 'Operating', 'bank_name' => 'First Bank',
            'account_number' => '123456789012', 'iban' => 'GB82WEST12345698765432', 'swift' => 'WESTGB2L',
        ])->assertCreated()
            ->assertJsonPath('data.account_number_masked', '••••9012')
            ->assertJsonPath('data.account.code', '1010')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonMissing(['account_number' => '123456789012'])
            ->json('data.id');

        $raw = DB::table('bank_accounts')->where('id', $id)->first();
        $this->assertSame('9012', $raw->account_number_last4);
        $this->assertStringNotContainsString('123456789012', $raw->account_number);
        $this->assertStringNotContainsString('98765432', $raw->iban);
        $this->assertSame('123456789012', BankAccount::forCompany($company->id)->findOrFail($id)->account_number);

        $this->getJson("/api/v1/bank-accounts/{$id}")->assertOk()->assertJsonPath('data.iban_masked', 'GB82••••••••••••••5432');
    }

    public function test_bank_account_must_link_a_cash_or_bank_gl_account_of_the_company(): void
    {
        [$manager, $company] = $this->financeUser(['bank.manage']);
        $other = $this->seededCompany();

        $this->actAs($manager, $company)
            ->postJson('/api/v1/bank-accounts', ['account_id' => $this->account($company, '6100')->id, 'name' => 'Nope'])
            ->assertUnprocessable()->assertJsonValidationErrors(['account_id']);

        $this->postJson('/api/v1/bank-accounts', ['account_id' => $this->account($other, '1010')->id, 'name' => 'Nope'])
            ->assertUnprocessable()->assertJsonValidationErrors(['account_id']);

        $this->assertDatabaseCount('bank_accounts', 0);
    }

    public function test_bank_accounts_are_tenant_isolated_and_permission_gated(): void
    {
        [$manager, $company] = $this->financeUser(['bank.manage']);
        $other = $this->seededCompany();
        $foreign = BankAccount::factory()->create(['company_id' => $other->id, 'account_id' => $this->account($other, '1010')->id]);

        $this->actAs($manager, $company)->getJson("/api/v1/bank-accounts/{$foreign->id}")->assertNotFound();
        $this->putJson("/api/v1/bank-accounts/{$foreign->id}", ['name' => 'Hijack'])->assertNotFound();
        $this->getJson('/api/v1/bank-accounts')->assertOk()->assertJsonCount(0, 'data');

        [$reader] = $this->financeUser(['accounts.view'], $company);
        $this->actAs($reader, $company)
            ->postJson('/api/v1/bank-accounts', ['account_id' => $this->account($company, '1010')->id, 'name' => 'X'])
            ->assertForbidden();
    }
}
