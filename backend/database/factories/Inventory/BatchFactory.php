<?php

namespace Database\Factories\Inventory;

use App\Models\Inventory\Batch;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Batch>
 */
class BatchFactory extends Factory
{
    protected $model = Batch::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'company_id' => fn (array $attributes) => Product::withoutTenancy()->find($attributes['product_id'])?->company_id,
            'batch_number' => 'LOT-'.Str::upper(Str::random(6)),
            'manufactured_at' => now()->subMonth()->toDateString(),
            'expires_at' => now()->addYear()->toDateString(),
        ];
    }
}
