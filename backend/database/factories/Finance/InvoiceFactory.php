<?php

namespace Database\Factories\Finance;

use App\Enums\Finance\InvoiceStatus;
use App\Models\Company;
use App\Models\Finance\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Header-only invoice (no lines, no journal). customer_id is a bare id: pass a
 * real customer id for anything that validates it. Use the API / actions for
 * posted invoices so the ledger stays consistent.
 *
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-60 days', 'now');
        $subtotal = fake()->randomFloat(2, 50, 5000);
        $tax = round($subtotal * 0.15, 2);
        $total = number_format($subtotal + $tax, 2, '.', '');

        return [
            'company_id' => Company::factory(),
            'number' => 'INV-'.now()->format('Y').'-'.fake()->unique()->numerify('9#####'),
            'customer_id' => fake()->numberBetween(1, 1000),
            'invoice_date' => $date->format('Y-m-d'),
            'due_date' => (clone $date)->modify('+30 days')->format('Y-m-d'),
            'currency' => 'USD',
            'status' => InvoiceStatus::Draft,
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'discount_total' => '0',
            'tax_total' => number_format($tax, 2, '.', ''),
            'total' => $total,
            'amount_paid' => '0',
            'balance_due' => $total,
        ];
    }

    public function pendingApproval(): static
    {
        return $this->state(['status' => InvoiceStatus::PendingApproval]);
    }
}
