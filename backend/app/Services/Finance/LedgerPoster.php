<?php

namespace App\Services\Finance;

use App\Actions\Finance\CreateJournalEntryAction;
use App\Actions\Finance\PostJournalEntryAction;
use App\Actions\Finance\ReverseJournalEntryAction;
use App\Models\Finance\JournalEntry;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Public entry point for sub-ledgers (invoices, bills, payments, payroll,
 * stock) to write to the general ledger. Creates AND posts in one transaction,
 * so callers either get a Posted entry or an exception and no rows.
 *
 *   $entry = app(LedgerPoster::class)->post([
 *       'entry_date'  => '2026-03-15',
 *       'memo'        => 'Invoice INV-2026-000012',
 *       'reference'   => 'INV-2026-000012',
 *       'source_type' => 'invoice',
 *       'source_id'   => $invoice->id,
 *       'branch_id'   => $invoice->branch_id,          // optional
 *       'lines' => [
 *           ['account_code' => ChartOfAccountsTemplate::ACCOUNTS_RECEIVABLE, 'debit' => '1150.00', 'credit' => '0',
 *            'partner_type' => 'customer', 'partner_id' => $invoice->customer_id],
 *           ['account_code' => ChartOfAccountsTemplate::SALES_REVENUE, 'debit' => '0', 'credit' => '1000.00'],
 *           ['account_code' => ChartOfAccountsTemplate::TAX_PAYABLE,   'debit' => '0', 'credit' => '150.00', 'tax_rate_id' => $rate->id],
 *       ],
 *   ]);
 *
 * Failures: Illuminate\Validation\ValidationException (unknown/inactive/foreign
 * account, bad amount, <2 lines), App\Exceptions\Finance\FinanceException
 * (422 finance.unbalanced, 409 finance.period_closed / finance.no_period).
 *
 * @phpstan-type PosterLine array{account_id?: int, account_code?: string, debit: string|int|float, credit: string|int|float, description?: ?string, branch_id?: ?int, partner_type?: ?string, partner_id?: ?int, tax_rate_id?: ?int}
 * @phpstan-type PosterPayload array{entry_date: string, memo?: ?string, reference?: ?string, source_type: string, source_id?: ?int, branch_id?: ?int, company_id?: int, lines: list<PosterLine>}
 */
class LedgerPoster
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly CreateJournalEntryAction $create,
        private readonly PostJournalEntryAction $post,
        private readonly ReverseJournalEntryAction $reverse,
    ) {}

    /**
     * Create and post a balanced entry. `company_id` defaults to the active
     * tenant; pass it explicitly from console/queue code without a tenant.
     *
     * @param  PosterPayload  $payload
     */
    public function post(array $payload, ?int $userId = null): JournalEntry
    {
        $companyId = (int) ($payload['company_id'] ?? $this->tenant->id()
            ?? throw new \LogicException('LedgerPoster::post needs a company context or an explicit company_id.'));

        $userId ??= auth()->id();

        return DB::transaction(function () use ($payload, $companyId, $userId): JournalEntry {
            $draft = $this->create->handle($payload, $companyId, $userId);

            return $this->post->handle($draft, $userId)->load('lines.account');
        });
    }

    /**
     * Reverse a posted entry (e.g. invoice voided). Returns the posted reversal.
     */
    public function reverse(JournalEntry $entry, ?string $date = null, ?string $memo = null, ?int $userId = null): JournalEntry
    {
        return $this->reverse->handle($entry, $date ?? now()->toDateString(), $memo, $userId ?? auth()->id());
    }

    /**
     * Every GL entry (any status, reversals included) a sub-ledger document produced.
     *
     * @return Collection<int, JournalEntry>
     */
    public function entriesFor(string $sourceType, int $sourceId, ?int $companyId = null): Collection
    {
        $companyId ??= (int) $this->tenant->id();

        return JournalEntry::forCompany($companyId)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->orderBy('id')
            ->get();
    }
}
