<?php

namespace App\Services\Procurement;

use App\Support\Money\Money;

/**
 * Pure, side-effect-free totals for procurement documents (purchase orders).
 *
 *   line_gross     = quantity × unit_price
 *   discount       = line_gross × discount_percent / 100
 *   line_subtotal  = line_gross − discount
 *   tax_amount     = line_subtotal × tax_rate / 100
 *   line_total     = line_subtotal + tax_amount
 *
 *   subtotal       = Σ line_subtotal        discount_total = Σ discount
 *   tax_total      = Σ tax_amount           total          = subtotal + tax_total + shipping_cost
 *
 * Every amount is a DECIMAL(18,4) string produced by Money (bcmath); the
 * caller never sees a float.
 */
final class DocumentTotals
{
    /**
     * @return array{line_gross: string, discount_amount: string, line_subtotal: string, tax_amount: string, line_total: string}
     */
    public function line(
        string|int|float $quantity,
        string|int|float $unitPrice,
        string|int|float|null $discountPercent = 0,
        string|int|float|null $taxRate = 0,
    ): array {
        $quantity = Money::normalize($quantity);
        $unitPrice = Money::normalize($unitPrice);
        $discountPercent = Money::normalize($discountPercent);
        $taxRate = Money::normalize($taxRate);

        $gross = Money::mul($quantity, $unitPrice);
        $discount = Money::percent($gross, $discountPercent);
        $subtotal = Money::sub($gross, $discount);
        $tax = Money::percent($subtotal, $taxRate);

        return [
            'line_gross' => $gross,
            'discount_amount' => $discount,
            'line_subtotal' => $subtotal,
            'tax_amount' => $tax,
            'line_total' => Money::add($subtotal, $tax),
        ];
    }

    /**
     * Compute every line and the document header totals in one pass.
     *
     * @param  array<int, array{quantity: string|int|float, unit_price: string|int|float, discount_percent?: string|int|float|null, tax_rate?: string|int|float|null}>  $lines
     * @return array{
     *     lines: array<int, array{line_gross: string, discount_amount: string, line_subtotal: string, tax_amount: string, line_total: string}>,
     *     subtotal: string, discount_total: string, tax_total: string, shipping_cost: string, total: string
     * }
     */
    public function document(array $lines, string|int|float|null $shippingCost = 0): array
    {
        $computed = [];

        foreach ($lines as $index => $line) {
            $computed[$index] = $this->line(
                $line['quantity'],
                $line['unit_price'],
                $line['discount_percent'] ?? 0,
                $line['tax_rate'] ?? 0,
            );
        }

        $subtotal = Money::sum(array_column($computed, 'line_subtotal'));
        $discountTotal = Money::sum(array_column($computed, 'discount_amount'));
        $taxTotal = Money::sum(array_column($computed, 'tax_amount'));
        $shipping = Money::normalize($shippingCost);

        return [
            'lines' => $computed,
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'shipping_cost' => $shipping,
            'total' => Money::sum([$subtotal, $taxTotal, $shipping]),
        ];
    }
}
