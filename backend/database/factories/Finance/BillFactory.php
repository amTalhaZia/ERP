<?php

namespace Database\Factories\Finance;

use App\Enums\Finance\BillStatus;
use App\Models\Company;
use App\Models\Finance\Bill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Header-only supplier bill (no lines, no journal).
 *
 * @extends Factory<Bill>
 */
class BillFactory extends Factory
{
    protected $model = Bill::class;

    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-60 days', 'now');
        $subtotal = fake()->randomFloat(2, 50, 5000);
        $tax = round($subtotal * 0.15, 2);
        $total = number_format($subtotal + $tax, 2, '.', '');

        return [
            'company_id' => Company::factory(),
            'number' => 'BILL-'.now()->format('Y').'-'.fake()->unique()->numerify('9#####'),
            'supplier_id' => fake()->numberBetween(1, 1000),
            'supplier_reference' => fake()->unique()->bothify('SINV-#####??'),
            'bill_date' => $date->format('Y-m-d'),
            'due_date' => (clone $date)->modify('+30 days')->format('Y-m-d'),
            'currency' => 'USD',
            'status' => BillStatus::Draft,
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'discount_total' => '0',
            'tax_total' => number_format($tax, 2, '.', ''),
            'total' => $total,
            'amount_paid' => '0',
            'balance_due' => $total,
        ];
    }
}
