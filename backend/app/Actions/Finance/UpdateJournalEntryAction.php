<?php

namespace App\Actions\Finance;

use App\Actions\Finance\Concerns\PreparesJournalLines;
use App\Exceptions\Finance\FinanceException;
use App\Models\Finance\JournalEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rewrites a Draft entry (header + full line set). Anything else → 409.
 */
class UpdateJournalEntryAction
{
    use PreparesJournalLines;

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(JournalEntry $entry, array $data): JournalEntry
    {
        return DB::transaction(function () use ($entry, $data): JournalEntry {
            $entry = JournalEntry::forCompany($entry->company_id)->whereKey($entry->id)->lockForUpdate()->firstOrFail();

            if (! $entry->isDraft()) {
                throw FinanceException::conflict('Only draft journal entries can be edited; post a reversing entry instead.');
            }

            $date = isset($data['entry_date']) ? Carbon::parse($data['entry_date'])->toDateString() : $entry->entry_date->toDateString();
            $attributes = ['entry_date' => $date, 'financial_period_id' => $this->resolvePeriodId($entry->company_id, $date)];

            foreach (['reference', 'memo', 'branch_id'] as $field) {
                if (array_key_exists($field, $data)) {
                    $attributes[$field] = $data[$field];
                }
            }

            if (isset($data['lines'])) {
                $prepared = $this->prepareLines($entry->company_id, $data['lines']);
                $entry->lines()->delete();
                $entry->lines()->createMany($prepared['lines']);
                $attributes['total_debit'] = $prepared['total_debit'];
                $attributes['total_credit'] = $prepared['total_credit'];
            }

            $entry->fill($attributes)->save();

            return $entry->refresh()->load('lines.account');
        });
    }
}
