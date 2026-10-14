<?php

namespace App\Actions\Finance;

use App\Enums\Finance\JournalEntryStatus;
use App\Exceptions\Finance\FinanceException;
use App\Models\Finance\JournalEntry;
use App\Models\Finance\JournalLine;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Corrections never edit a posted entry: a mirror entry (debits ↔ credits) is
 * created and posted on the given date, both entries are linked and the
 * original is marked Reversed. Source type/id are carried over so sub-ledgers
 * can find every GL entry for their document.
 */
class ReverseJournalEntryAction
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CreateJournalEntryAction $create,
        private readonly PostJournalEntryAction $post,
    ) {}

    public function handle(JournalEntry $entry, string $date, ?string $memo = null, ?int $userId = null): JournalEntry
    {
        return DB::transaction(function () use ($entry, $date, $memo, $userId): JournalEntry {
            $entry = JournalEntry::forCompany($entry->company_id)->whereKey($entry->id)->lockForUpdate()->firstOrFail();

            if (! $entry->isPosted()) {
                throw FinanceException::conflict("Only posted entries can be reversed; {$entry->number} is {$entry->status->value}.");
            }

            $date = Carbon::parse($date)->toDateString();

            if ($date < $entry->entry_date->toDateString()) {
                throw FinanceException::conflict('A reversal cannot be dated before the entry it reverses.');
            }

            $mirror = $entry->lines()->get()->map(fn (JournalLine $line) => [
                'account_id' => $line->account_id,
                'debit' => $line->credit,
                'credit' => $line->debit,
                'description' => $line->description,
                'branch_id' => $line->branch_id,
                'partner_type' => $line->partner_type,
                'partner_id' => $line->partner_id,
                'tax_rate_id' => $line->tax_rate_id,
            ])->all();

            $reversal = $this->create->handle([
                'entry_date' => $date,
                'reference' => 'REV '.$entry->number,
                'memo' => $memo ?? "Reversal of {$entry->number}",
                'branch_id' => $entry->branch_id,
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
                'reverses_entry_id' => $entry->id,
                'lines' => $mirror,
            ], $entry->company_id, $userId);

            $reversal = $this->post->handle($reversal, $userId);

            $entry->fill(['status' => JournalEntryStatus::Reversed, 'reversed_by_entry_id' => $reversal->id])->save();

            $this->audit->log('journals.reversed', $entry, companyId: $entry->company_id, context: [
                'number' => $entry->number,
                'reversal_id' => $reversal->id,
                'reversal_number' => $reversal->number,
                'reversal_date' => $date,
                'total' => $entry->total_debit,
            ]);

            return $reversal->load('lines.account');
        });
    }
}
