<?php

namespace App\Actions\Finance;

use App\Enums\Finance\JournalEntryStatus;
use App\Exceptions\Finance\FinanceException;
use App\Models\Finance\Account;
use App\Models\Finance\FinancialPeriod;
use App\Models\Finance\JournalEntry;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;

/**
 * Draft → Posted. Re-validates everything that matters for the ledger inside
 * a row-locked transaction: balanced and non-empty (422 finance.unbalanced),
 * open period (409 finance.period_closed / finance.no_period), active accounts.
 */
class PostJournalEntryAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(JournalEntry $entry, ?int $userId = null): JournalEntry
    {
        return DB::transaction(function () use ($entry, $userId): JournalEntry {
            $entry = JournalEntry::forCompany($entry->company_id)->whereKey($entry->id)->lockForUpdate()->firstOrFail();

            if (! $entry->isDraft()) {
                throw FinanceException::conflict("Journal entry {$entry->number} is already {$entry->status->value}.");
            }

            $period = FinancialPeriod::covering($entry->company_id, $entry->entry_date->toDateString());

            if ($period === null) {
                throw FinanceException::noPeriod($entry->entry_date->toDateString());
            }

            if (! $period->isOpen()) {
                throw FinanceException::periodClosed($period->name);
            }

            $lines = $entry->lines()->get();
            $totalDebit = Money::sum($lines->pluck('debit')->all());
            $totalCredit = Money::sum($lines->pluck('credit')->all());

            if ($lines->count() < 2 || Money::isZero($totalDebit) || ! Money::equals($totalDebit, $totalCredit)) {
                throw FinanceException::unbalanced($totalDebit, $totalCredit);
            }

            $inactive = Account::forCompany($entry->company_id)
                ->whereIn('id', $lines->pluck('account_id')->unique()->all())
                ->where('is_active', false)
                ->pluck('code')
                ->all();

            if ($inactive !== []) {
                throw FinanceException::conflict('Inactive accounts on entry: '.implode(', ', $inactive), 'finance.inactive_account');
            }

            $entry->fill([
                'status' => JournalEntryStatus::Posted,
                'financial_period_id' => $period->id,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'posted_by' => $userId,
                'posted_at' => now(),
            ])->save();

            $this->audit->log('journals.posted', $entry, companyId: $entry->company_id, context: [
                'number' => $entry->number,
                'entry_date' => $entry->entry_date->toDateString(),
                'period' => $period->name,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'lines' => $lines->count(),
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
            ]);

            return $entry;
        });
    }
}
