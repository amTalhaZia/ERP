<?php

namespace App\Services\Finance\Receivables;

use App\Support\Money\Money;

/**
 * Server-side totals for invoices, bills and expense claims.
 *
 * Rounding policy (documented, deterministic):
 *   gross          = round2(quantity × unit_price)
 *   line_subtotal  = round2(gross × (1 − discount% / 100))
 *   discount       = gross − line_subtotal
 *   tax_amount     = round2(Money::percent(line_subtotal, tax_rate))
 *   line_total     = line_subtotal + tax_amount
 * Document totals are plain sums of the rounded line values, so
 * total = subtotal + tax_total always holds exactly and the posted journal
 * (built from the same stored values) always balances. Rounding uses
 * Money::round (half-up) to 2 decimals; columns store 4 decimals.
 */
final class DocumentTotalsCalculator
{
    public const DECIMALS = 2;

    /**
     * @return array{line_subtotal: string, discount_amount: string, tax_amount: string, line_total: string}
     */
    public function line(string|int|float $quantity, string|int|float $unitPrice, string|int|float|null $discountPercent, string|int|float|null $taxRate): array
    {
        $gross = $this->round(Money::mul(Money::normalize($quantity), Money::normalize($unitPrice)));
        $discount = Money::normalize($discountPercent);
        $factor = bcsub('1', bcdiv($discount, '100', 8), 8);
        $subtotal = $this->round(bcmul($gross, $factor, 8));
        $tax = $this->round(Money::percent($subtotal, Money::normalize($taxRate)));

        return [
            'line_subtotal' => $subtotal,
            'discount_amount' => Money::sub($gross, $subtotal),
            'tax_amount' => $tax,
            'line_total' => Money::add($subtotal, $tax),
        ];
    }

    /**
     * Net amount (no quantity / discount) plus tax — expense claim lines.
     *
     * @return array{tax_amount: string, line_total: string}
     */
    public function amountWithTax(string|int|float $amount, string|int|float|null $taxRate): array
    {
        $net = $this->round(Money::normalize($amount));
        $tax = $this->round(Money::percent($net, Money::normalize($taxRate)));

        return ['tax_amount' => $tax, 'line_total' => Money::add($net, $tax)];
    }

    /**
     * @param  iterable<array{line_subtotal: string, discount_amount?: string, tax_amount: string}>  $lines
     * @return array{subtotal: string, discount_total: string, tax_total: string, total: string}
     */
    public function totals(iterable $lines): array
    {
        $subtotal = Money::zero();
        $discount = Money::zero();
        $tax = Money::zero();

        foreach ($lines as $line) {
            $subtotal = Money::add($subtotal, (string) $line['line_subtotal']);
            $discount = Money::add($discount, (string) ($line['discount_amount'] ?? '0'));
            $tax = Money::add($tax, (string) $line['tax_amount']);
        }

        return [
            'subtotal' => $subtotal,
            'discount_total' => $discount,
            'tax_total' => $tax,
            'total' => Money::add($subtotal, $tax),
        ];
    }

    private function round(string $amount): string
    {
        return Money::normalize(Money::round($amount, self::DECIMALS));
    }
}
