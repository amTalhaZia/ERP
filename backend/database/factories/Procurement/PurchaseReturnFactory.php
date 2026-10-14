<?php

namespace Database\Factories\Procurement;

use App\Enums\Procurement\PurchaseReturnReason;
use App\Enums\Procurement\PurchaseReturnStatus;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseReturn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseReturn>
 */
class PurchaseReturnFactory extends Factory
{
    protected $model = PurchaseReturn::class;

    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory()->approved(),
            'company_id' => fn (array $attributes) => PurchaseOrder::withoutTenancy()->findOrFail($attributes['purchase_order_id'])->company_id,
            'number' => 'PRT-'.now()->format('Y').'-'.fake()->unique()->numerify('######'),
            'return_date' => now()->toDateString(),
            'reason' => PurchaseReturnReason::Damaged,
            'status' => PurchaseReturnStatus::Draft,
            'total' => '0.0000',
            'created_by' => fn (array $attributes) => PurchaseOrder::withoutTenancy()->findOrFail($attributes['purchase_order_id'])->created_by,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(['status' => PurchaseReturnStatus::Confirmed, 'confirmed_at' => now()]);
    }
}
