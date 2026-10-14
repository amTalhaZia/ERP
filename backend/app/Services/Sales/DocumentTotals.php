<?php

namespace App\Services\Sales;

use App\Support\Money\Money;

/**
 * Server-side totals for quotations and sales orders (never trust client math).
 *
 *   line_subtotal = quantity × unit_price × (1 − discount_percent / 100)
 *   tax_amount    = Money::percent(line_subtotal, tax_rate)
 *   line_total    = line_subtotal + tax_amount
 *   document      = Money::sum over lines
 *
 * All amounts are DECIMAL(18,4) strings.
 */
final class DocumentTotals
{
    /**
     * @param  array<int, array<string, mixed>>  $lines  each with quantity, unit_price and optional discount_percent, tax_rate
     * @return array{
     *     lines: array<int, array<string, mixed>>,
     *     subtotal: string,
     *     discount_total: string,
     *     tax_total: string,
     *     total: string
     * } `lines` are the input rows with normalized amounts plus line_no, discount_amount, line_subtotal, tax_amount, line_total
     */
    public function compute(array $lines): array
    {
        $computed = [];
        $subtotals = [];
        $discounts = [];
        $taxes = [];

        // FormRequest::validated() can reorder keyed wildcard rows (rows with optional
        // keys like product_id may come out first); restore the client's input order.
        ksort($lines);

        foreach (array_values($lines) as $index => $line) {
            $amounts = $this->line(
                $line['quantity'] ?? 0,
                $line['unit_price'] ?? 0,
                $line['discount_percent'] ?? null,
                $line['tax_rate'] ?? null,
            );

            $computed[] = array_merge($line, [
                'line_no' => $index + 1,
                'quantity' => $amounts['quantity'],
                'unit_price' => $amounts['unit_price'],
                'discount_percent' => $amounts['discount_percent'],
                'tax_rate' => $amounts['tax_rate'],
                'discount_amount' => $amounts['discount_amount'],
                'line_subtotal' => $amounts['line_subtotal'],
                'tax_amount' => $amounts['tax_amount'],
                'line_total' => $amounts['line_total'],
            ]);

            $subtotals[] = $amounts['line_subtotal'];
            $discounts[] = $amounts['discount_amount'];
            $taxes[] = $amounts['tax_amount'];
        }

        $subtotal = Money::sum($subtotals);
        $taxTotal = Money::sum($taxes);

        return [
            'lines' => $computed,
            'subtotal' => $subtotal,
            'discount_total' => Money::sum($discounts),
            'tax_total' => $taxTotal,
            'total' => Money::add($subtotal, $taxTotal),
        ];
    }

    /**
     * @return array{
     *     quantity: string, unit_price: string, discount_percent: string, tax_rate: string,
     *     gross: string, discount_amount: string, line_subtotal: string, tax_amount: string, line_total: string
     * }
     */
    public function line(
        string|int|float|null $quantity,
        string|int|float|null $unitPrice,
        string|int|float|null $discountPercent = null,
        string|int|float|null $taxRate = null,
    ): array {
        $quantity = Money::normalize($quantity);
        $unitPrice = Money::normalize($unitPrice);
        $discountPercent = Money::normalize($discountPercent);
        $taxRate = Money::normalize($taxRate);

        $gross = Money::mul($quantity, $unitPrice);
        $discountAmount = Money::percent($gross, $discountPercent);
        $lineSubtotal = Money::sub($gross, $discountAmount);
        $taxAmount = Money::percent($lineSubtotal, $taxRate);

        return [
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_percent' => bcadd($discountPercent, '0', 2),
            'tax_rate' => $taxRate,
            'gross' => $gross,
            'discount_amount' => $discountAmount,
            'line_subtotal' => $lineSubtotal,
            'tax_amount' => $taxAmount,
            'line_total' => Money::add($lineSubtotal, $taxAmount),
        ];
    }
}
