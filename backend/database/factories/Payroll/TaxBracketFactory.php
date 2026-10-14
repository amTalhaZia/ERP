<?php

namespace Database\Factories\Payroll;

use App\Models\Payroll\TaxBracket;
use App\Models\Payroll\TaxTable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxBracket>
 */
class TaxBracketFactory extends Factory
{
    protected $model = TaxBracket::class;

    public function definition(): array
    {
        return [
            'tax_table_id' => TaxTable::factory(),
            'company_id' => fn (array $attrs) => TaxTable::withoutTenancy()->find($attrs['tax_table_id'])->company_id,
            'from_amount' => '0.0000',
            'to_amount' => null,
            'rate' => '10.0000',
            'fixed_amount' => '0.0000',
            'sort_order' => 1,
        ];
    }
}
