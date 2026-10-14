<?php

namespace Database\Factories\Procurement;

use App\Models\Procurement\PurchaseRequisition;
use App\Models\Procurement\PurchaseRequisitionLine;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseRequisitionLine>
 */
class PurchaseRequisitionLineFactory extends Factory
{
    protected $model = PurchaseRequisitionLine::class;

    public function definition(): array
    {
        $quantity = (string) fake()->numberBetween(1, 20);
        $price = fake()->randomElement(['10.0000', '25.5000', '99.9900', '4.2500']);

        return [
            'purchase_requisition_id' => PurchaseRequisition::factory(),
            'company_id' => fn (array $attributes) => PurchaseRequisition::withoutTenancy()->findOrFail($attributes['purchase_requisition_id'])->company_id,
            'line_no' => 1,
            'product_id' => null,
            'description' => fake()->words(3, true),
            'quantity' => $quantity,
            'uom' => 'pcs',
            'estimated_unit_price' => $price,
            'line_total' => Money::mul($quantity, $price),
        ];
    }
}
