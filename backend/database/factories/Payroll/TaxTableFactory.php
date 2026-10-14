<?php

namespace Database\Factories\Payroll;

use App\Enums\Payroll\TaxTablePeriod;
use App\Models\Company;
use App\Models\Payroll\TaxTable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxTable>
 */
class TaxTableFactory extends Factory
{
    protected $model = TaxTable::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'Withholding '.fake()->year(),
            'effective_from' => '2026-01-01',
            'period' => TaxTablePeriod::Monthly,
            'is_active' => true,
        ];
    }

    /**
     * Add brackets: list of [from, to|null, rate, fixed?].
     *
     * @param  list<array{0: string, 1: ?string, 2: string, 3?: string}>  $brackets
     */
    public function withBrackets(array $brackets): static
    {
        return $this->afterCreating(function (TaxTable $table) use ($brackets): void {
            foreach ($brackets as $i => $b) {
                $table->brackets()->create([
                    'company_id' => $table->company_id,
                    'from_amount' => $b[0],
                    'to_amount' => $b[1],
                    'rate' => $b[2],
                    'fixed_amount' => $b[3] ?? '0',
                    'sort_order' => $i + 1,
                ]);
            }
        });
    }
}
