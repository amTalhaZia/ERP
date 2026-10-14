<?php

namespace Database\Factories\Procurement;

use App\Enums\Procurement\GoodsReceiptStatus;
use App\Models\Procurement\GoodsReceipt;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoodsReceipt>
 */
class GoodsReceiptFactory extends Factory
{
    protected $model = GoodsReceipt::class;

    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory()->approved(),
            'company_id' => fn (array $attributes) => PurchaseOrder::withoutTenancy()->findOrFail($attributes['purchase_order_id'])->company_id,
            'number' => 'GRN-'.now()->format('Y').'-'.fake()->unique()->numerify('######'),
            'received_date' => now()->toDateString(),
            'status' => GoodsReceiptStatus::Draft,
            'received_by' => fn (array $attributes) => PurchaseOrder::withoutTenancy()->findOrFail($attributes['purchase_order_id'])->created_by,
            'supplier_delivery_note' => fake()->bothify('DN-#####'),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(['status' => GoodsReceiptStatus::Confirmed, 'confirmed_at' => now()]);
    }
}
