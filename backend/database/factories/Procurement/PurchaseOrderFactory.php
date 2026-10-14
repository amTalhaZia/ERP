<?php

namespace Database\Factories\Procurement;

use App\Enums\Procurement\PurchaseOrderStatus;
use App\Models\Company;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderLine;
use App\Models\Procurement\Supplier;
use App\Models\User;
use App\Services\Procurement\DocumentTotals;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'number' => 'PO-'.now()->format('Y').'-'.fake()->unique()->numerify('######'),
            'supplier_id' => fn (array $attributes) => Supplier::factory()->create(['company_id' => $attributes['company_id']])->id,
            'order_date' => now()->toDateString(),
            'expected_date' => now()->addDays(7)->toDateString(),
            'status' => PurchaseOrderStatus::Draft,
            'currency' => 'USD',
            'subtotal' => '0.0000',
            'discount_total' => '0.0000',
            'tax_total' => '0.0000',
            'shipping_cost' => '0.0000',
            'total' => '0.0000',
            'payment_terms_days' => 30,
            'created_by' => fn (array $attributes) => User::factory()->inCompany(Company::query()->findOrFail($attributes['company_id']))->create()->id,
        ];
    }

    public function submitted(): static
    {
        return $this->state(['status' => PurchaseOrderStatus::Submitted, 'submitted_at' => now()]);
    }

    public function approved(?User $approver = null): static
    {
        return $this->state([
            'status' => PurchaseOrderStatus::Approved,
            'submitted_at' => now()->subHour(),
            'approved_by' => $approver?->id,
            'approved_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => PurchaseOrderStatus::Cancelled, 'cancelled_at' => now(), 'cancelled_reason' => 'No longer required']);
    }

    /**
     * Attach lines and recompute header totals.
     *
     * @param  array<int, array<string, mixed>>  $lines  overrides per line (quantity, unit_price, discount_percent, tax_rate, …)
     */
    public function withLines(array $lines = [['quantity' => '10', 'unit_price' => '5.0000']]): static
    {
        return $this->afterCreating(function (PurchaseOrder $order) use ($lines): void {
            $totals = app(DocumentTotals::class)->document($lines, (string) $order->shipping_cost);

            foreach ($lines as $index => $line) {
                PurchaseOrderLine::factory()->create([
                    'company_id' => $order->company_id,
                    'purchase_order_id' => $order->id,
                    'line_no' => $index + 1,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'discount_percent' => $line['discount_percent'] ?? '0.00',
                    'tax_rate' => $line['tax_rate'] ?? '0.0000',
                    'line_subtotal' => $totals['lines'][$index]['line_subtotal'],
                    'tax_amount' => $totals['lines'][$index]['tax_amount'],
                    'line_total' => $totals['lines'][$index]['line_total'],
                ] + array_intersect_key($line, array_flip(['product_id', 'description', 'uom', 'quantity_received', 'quantity_returned'])));
            }

            $order->forceFill([
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'tax_total' => $totals['tax_total'],
                'total' => $totals['total'],
            ])->save();
        });
    }
}
