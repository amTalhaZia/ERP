<?php

namespace Tests\Feature\Finance\Receivables\Concerns;

use App\Models\Company;
use App\Models\Finance\BankAccount;
use App\Models\Finance\TaxRate;
use App\Models\Inventory\Product;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Finance\Concerns\InteractsWithLedger;

/**
 * AR/AP arrange helpers on top of InteractsWithLedger. Migrates core +
 * finance + sales (customers) + procurement (suppliers) + AR/AP schema only.
 */
trait InteractsWithReceivables
{
    use InteractsWithLedger;

    /** @var array<int, string> */
    protected array $allPermissions = [
        'invoices.view', 'invoices.create', 'invoices.update', 'invoices.approve', 'invoices.delete',
        'bills.view', 'bills.create', 'bills.approve', 'payments.view', 'payments.create',
        'expenses.view', 'expenses.submit', 'expenses.approve', 'reports.financial.view',
    ];

    /**
     * @return array{0: User, 1: Company}
     */
    protected function accountant(?Company $company = null, ?array $permissions = null): array
    {
        return $this->financeUser($permissions ?? $this->allPermissions, $company);
    }

    protected function customer(Company $company, int $termsDays = 30, string $name = 'Acme Retail'): int
    {
        return (int) DB::table('customers')->insertGetId([
            'company_id' => $company->id, 'code' => 'C-'.Str::upper(Str::random(6)), 'name' => $name,
            'currency' => 'USD', 'payment_terms_days' => $termsDays, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function supplier(Company $company, string $name = 'Paper Mill Ltd'): int
    {
        return (int) DB::table('suppliers')->insertGetId([
            'company_id' => $company->id, 'code' => 'S-'.Str::upper(Str::random(6)), 'name' => $name,
            'currency' => 'USD', 'payment_terms_days' => 30, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** A goods product in the company's catalogue (product_id references are TenantExists-checked). */
    protected function product(Company $company): int
    {
        return Product::factory()->create(['company_id' => $company->id])->id;
    }

    protected function bank(Company $company): BankAccount
    {
        return BankAccount::factory()->create([
            'company_id' => $company->id,
            'account_id' => $this->account($company, '1010')->id,
            'currency' => 'USD',
        ]);
    }

    protected function standardTax(Company $company): TaxRate
    {
        return TaxRate::forCompany($company->id)->where('code', 'STD')->firstOrFail();
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $lines
     * @return array<string, mixed>
     */
    protected function invoicePayload(Company $company, int $customerId, ?array $lines = null, string $date = '2026-10-01'): array
    {
        return [
            'customer_id' => $customerId,
            'invoice_date' => $date,
            'lines' => $lines ?? [[
                'description' => 'Consulting hours',
                'quantity' => '2',
                'unit_price' => '100.00',
                'discount_percent' => '10',
                'tax_rate_id' => $this->standardTax($company)->id,
            ]],
        ];
    }

    /**
     * Create + approve through the API; returns the invoice JSON.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function approvedInvoice(User $user, Company $company, int $customerId, array $overrides = []): array
    {
        $id = $this->actAs($user, $company)
            ->postJson('/api/v1/invoices', array_merge($this->invoicePayload($company, $customerId), $overrides))
            ->assertCreated()->json('data.id');

        return $this->postJson("/api/v1/invoices/{$id}/approve")->assertOk()->json('data');
    }

    /**
     * @param  array<int, array{type: string, id: int, amount: string}>  $allocations
     */
    protected function recordReceipt(Company $company, int $customerId, BankAccount $bank, string $amount, array $allocations, string $date = '2026-10-10'): TestResponse
    {
        return $this->postJson('/api/v1/payments', [
            'partner_type' => 'customer',
            'partner_id' => $customerId,
            'payment_date' => $date,
            'method' => 'bank_transfer',
            'bank_account_id' => $bank->id,
            'amount' => $amount,
            'allocations' => $allocations,
        ]);
    }

    /**
     * [account code => [debit, credit]] for one journal entry.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    protected function journalByCode(int $entryId): array
    {
        return DB::table('journal_lines')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_entry_id', $entryId)
            ->get(['accounts.code', 'debit', 'credit'])
            ->mapWithKeys(fn ($row) => [$row->code => [Money::normalize((string) $row->debit), Money::normalize((string) $row->credit)]])
            ->all();
    }

    protected function assertLedgerBalanced(Company $company): void
    {
        $totals = DB::table('journal_lines')->where('company_id', $company->id)
            ->selectRaw('COALESCE(SUM(debit), 0) as d, COALESCE(SUM(credit), 0) as c')->first();

        $this->assertSame(Money::normalize((string) $totals->d), Money::normalize((string) $totals->c), 'Trial balance is out of balance.');
    }

    /** Net balance (debit − credit) of an account across all posted lines. */
    protected function accountBalance(Company $company, string $code): string
    {
        $row = DB::table('journal_lines')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_lines.company_id', $company->id)->where('accounts.code', $code)
            ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as balance')->first();

        return Money::normalize((string) $row->balance);
    }
}
