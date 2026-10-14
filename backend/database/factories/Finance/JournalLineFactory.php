<?php

namespace Database\Factories\Finance;

use App\Models\Finance\Account;
use App\Models\Finance\JournalEntry;
use App\Models\Finance\JournalLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalLine>
 */
class JournalLineFactory extends Factory
{
    protected $model = JournalLine::class;

    public function definition(): array
    {
        return [
            'journal_entry_id' => JournalEntry::factory(),
            'company_id' => fn (array $attrs) => JournalEntry::withoutTenancy()->findOrFail($attrs['journal_entry_id'])->company_id,
            'account_id' => fn (array $attrs) => Account::factory()->create(['company_id' => $attrs['company_id']])->id,
            'line_no' => 1,
            'description' => fake()->sentence(3),
            'debit' => '100.0000',
            'credit' => '0.0000',
        ];
    }

    public function debit(string $amount): static
    {
        return $this->state(['debit' => $amount, 'credit' => '0.0000']);
    }

    public function credit(string $amount): static
    {
        return $this->state(['debit' => '0.0000', 'credit' => $amount]);
    }
}
