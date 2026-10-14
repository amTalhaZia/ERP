<?php

namespace Database\Factories\Sales;

use App\Enums\Sales\DeliveryStatus;
use App\Models\Sales\Delivery;
use App\Models\Sales\SalesOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Delivery>
 */
class DeliveryFactory extends Factory
{
    protected $model = Delivery::class;

    public function definition(): array
    {
        return [
            'sales_order_id' => SalesOrder::factory()->approved(),
            'company_id' => fn (array $attributes) => SalesOrder::withoutTenancy()->find($attributes['sales_order_id'])?->company_id,
            'number' => 'DLV-T-'.Str::upper(Str::random(8)),
            'delivery_date' => now()->toDateString(),
            'status' => DeliveryStatus::Pending,
            'carrier' => fake()->randomElement(['DHL', 'UPS', 'FedEx', 'Own fleet']),
            'tracking_number' => Str::upper(Str::random(12)),
        ];
    }
}
