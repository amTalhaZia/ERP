<?php

namespace Database\Factories\Procurement;

use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderLine>
 */
class PurchaseOrderLineFactory extends Factory
{
    protected $model = PurchaseOrderLine::class;

    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'company_id' => fn (array $attributes) => PurchaseOrder::withoutTenancy()->findOrFail($attributes['purchase_order_id'])->company_id,
            'line_no' => 1,
            'product_id' => null,
            'description' => fake()->words(3, true),
            'quantity' => '10.0000',
            'uom' => 'pcs',
            'unit_price' => '5.0000',
            'discount_percent' => '0.00',
            'tax_rate' => '0.0000',
            'line_subtotal' => '50.0000',
            'tax_amount' => '0.0000',
            'line_total' => '50.0000',
            'quantity_received' => '0.0000',
            'quantity_returned' => '0.0000',
        ];
    }
}
