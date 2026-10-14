<?php

namespace Database\Factories\Finance;

use App\Enums\Finance\JournalEntryStatus;
use App\Enums\Finance\JournalSourceType;
use App\Models\Company;
use App\Models\Finance\JournalEntry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JournalEntry>
 *
 * Creates the header only; add lines with JournalLine::factory() or, for a
 * realistic balanced + posted entry, go through LedgerPoster in tests.
 */
class JournalEntryFactory extends Factory
{
    protected $model = JournalEntry::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'number' => 'JE-'.now()->format('Y').'-'.Str::upper(Str::random(6)),
            'entry_date' => now()->toDateString(),
            'reference' => fake()->bothify('REF-####'),
            'memo' => fake()->sentence(),
            'status' => JournalEntryStatus::Draft,
            'source_type' => JournalSourceType::Manual->value,
            'total_debit' => '0.0000',
            'total_credit' => '0.0000',
        ];
    }

    public function posted(): static
    {
        return $this->state(['status' => JournalEntryStatus::Posted, 'posted_at' => now()]);
    }
}
