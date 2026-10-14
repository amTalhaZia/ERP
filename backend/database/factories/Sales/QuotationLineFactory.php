<?php

namespace Database\Factories\Sales;

use App\Models\Sales\Quotation;
use App\Models\Sales\QuotationLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuotationLine>
 */
class QuotationLineFactory extends Factory
{
    protected $model = QuotationLine::class;

    public function definition(): array
    {
        return [
            'quotation_id' => Quotation::factory(),
            'company_id' => fn (array $attributes) => Quotation::withoutTenancy()->find($attributes['quotation_id'])?->company_id,
            'line_no' => 1,
            'description' => fake()->words(3, true),
            'quantity' => '1.0000',
            'uom' => 'pcs',
            'unit_price' => '100.0000',
            'discount_percent' => '0.00',
            'tax_rate' => '0.0000',
            'line_subtotal' => '100.0000',
            'tax_amount' => '0.0000',
            'line_total' => '100.0000',
        ];
    }
}
