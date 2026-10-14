<?php

namespace Database\Factories\Procurement;

use App\Models\Procurement\PurchaseOrderLine;
use App\Models\Procurement\PurchaseReturn;
use App\Models\Procurement\PurchaseReturnLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseReturnLine>
 */
class PurchaseReturnLineFactory extends Factory
{
    protected $model = PurchaseReturnLine::class;

    public function definition(): array
    {
        return [
            'purchase_return_id' => PurchaseReturn::factory(),
            'company_id' => fn (array $attributes) => PurchaseReturn::withoutTenancy()->findOrFail($attributes['purchase_return_id'])->company_id,
            'purchase_order_line_id' => function (array $attributes) {
                $return = PurchaseReturn::withoutTenancy()->findOrFail($attributes['purchase_return_id']);

                return PurchaseOrderLine::factory()->create([
                    'company_id' => $return->company_id,
                    'purchase_order_id' => $return->purchase_order_id,
                    'quantity_received' => '10.0000',
                ])->id;
            },
            'product_id' => null,
            'description' => fake()->words(3, true),
            'quantity' => '2.0000',
            'unit_cost' => '5.0000',
            'line_total' => '10.0000',
        ];
    }
}
