<?php

namespace Database\Factories\Procurement;

use App\Models\Procurement\GoodsReceipt;
use App\Models\Procurement\GoodsReceiptLine;
use App\Models\Procurement\PurchaseOrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoodsReceiptLine>
 */
class GoodsReceiptLineFactory extends Factory
{
    protected $model = GoodsReceiptLine::class;

    public function definition(): array
    {
        return [
            'goods_receipt_id' => GoodsReceipt::factory(),
            'company_id' => fn (array $attributes) => GoodsReceipt::withoutTenancy()->findOrFail($attributes['goods_receipt_id'])->company_id,
            'purchase_order_line_id' => function (array $attributes) {
                $receipt = GoodsReceipt::withoutTenancy()->findOrFail($attributes['goods_receipt_id']);

                return PurchaseOrderLine::factory()->create([
                    'company_id' => $receipt->company_id,
                    'purchase_order_id' => $receipt->purchase_order_id,
                ])->id;
            },
            'product_id' => null,
            'description' => fake()->words(3, true),
            'quantity_ordered' => '10.0000',
            'quantity_received' => '10.0000',
            'quantity_rejected' => '0.0000',
            'unit_cost' => '5.0000',
        ];
    }
}
