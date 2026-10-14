<?php

namespace Database\Factories\Sales;

use App\Models\Sales\Delivery;
use App\Models\Sales\DeliveryLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pass `delivery_id` and `sales_order_line_id` belonging to the same order.
 *
 * @extends Factory<DeliveryLine>
 */
class DeliveryLineFactory extends Factory
{
    protected $model = DeliveryLine::class;

    public function definition(): array
    {
        return [
            'delivery_id' => Delivery::factory(),
            'company_id' => fn (array $attributes) => Delivery::withoutTenancy()->find($attributes['delivery_id'])?->company_id,
            'quantity' => '1.0000',
        ];
    }
}
