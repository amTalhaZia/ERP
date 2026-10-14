<?php

namespace Database\Factories\Inventory;

use App\Enums\Inventory\SerialStatus;
use App\Models\Inventory\Product;
use App\Models\Inventory\SerialNumber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SerialNumber>
 */
class SerialNumberFactory extends Factory
{
    protected $model = SerialNumber::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'company_id' => fn (array $attributes) => Product::withoutTenancy()->find($attributes['product_id'])?->company_id,
            'serial' => 'SN-'.Str::upper(Str::random(10)),
            'status' => SerialStatus::InStock,
        ];
    }
}
