<?php

namespace Database\Factories\Sales;

use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesOrderLine>
 */
class SalesOrderLineFactory extends Factory
{
    protected $model = SalesOrderLine::class;

    public function definition(): array
    {
        return [
            'sales_order_id' => SalesOrder::factory(),
            'company_id' => fn (array $attributes) => SalesOrder::withoutTenancy()->find($attributes['sales_order_id'])?->company_id,
            'line_no' => 1,
            'description' => fake()->words(3, true),
            'quantity' => '10.0000',
            'quantity_delivered' => '0.0000',
            'uom' => 'pcs',
            'unit_price' => '50.0000',
            'discount_percent' => '0.00',
            'tax_rate' => '0.0000',
            'line_subtotal' => '500.0000',
            'tax_amount' => '0.0000',
            'line_total' => '500.0000',
        ];
    }
}
