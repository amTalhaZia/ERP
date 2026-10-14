<?php

namespace App\Actions\Finance;

use App\Actions\Finance\Concerns\PreparesJournalLines;
use App\Enums\Finance\JournalEntryStatus;
use App\Enums\Finance\JournalSourceType;
use App\Models\Finance\JournalEntry;
use App\Support\Numbering\DocumentNumberer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Creates a Draft journal entry with its lines and a JE-YYYY-nnnnnn number.
 *
 * @phpstan-type LineInput array{account_id?: int, account_code?: string, debit?: string|int|float|null, credit?: string|int|float|null, description?: ?string, branch_id?: ?int, partner_type?: ?string, partner_id?: ?int, tax_rate_id?: ?int}
 * @phpstan-type EntryInput array{entry_date: string, memo?: ?string, reference?: ?string, branch_id?: ?int, source_type?: ?string, source_id?: ?int, reverses_entry_id?: ?int, lines: list<LineInput>}
 */
class CreateJournalEntryAction
{
    use PreparesJournalLines;

    public function __construct(private readonly DocumentNumberer $numberer) {}

    /**
     * @param  EntryInput  $data
     */
    public function handle(array $data, int $companyId, ?int $userId = null): JournalEntry
    {
        return DB::transaction(function () use ($data, $companyId, $userId): JournalEntry {
            $prepared = $this->prepareLines($companyId, $data['lines']);
            $date = Carbon::parse($data['entry_date'])->toDateString();

            $entry = JournalEntry::query()->create([
                'company_id' => $companyId,
                'number' => $this->numberer->next('journal', companyId: $companyId),
                'entry_date' => $date,
                'financial_period_id' => $this->resolvePeriodId($companyId, $date),
                'reference' => $data['reference'] ?? null,
                'memo' => $data['memo'] ?? null,
                'status' => JournalEntryStatus::Draft,
                'source_type' => $data['source_type'] ?? JournalSourceType::Manual->value,
                'source_id' => $data['source_id'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
                'reverses_entry_id' => $data['reverses_entry_id'] ?? null,
                'total_debit' => $prepared['total_debit'],
                'total_credit' => $prepared['total_credit'],
                'created_by' => $userId,
            ]);

            $entry->lines()->createMany($prepared['lines']);

            return $entry->load('lines.account');
        });
    }
}
