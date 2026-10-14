<?php

namespace App\Services\Payroll;

use App\Enums\Payroll\TaxTablePeriod;
use App\Models\Payroll\TaxBracket;
use App\Models\Payroll\TaxTable;
use App\Support\Money\Money;

/**
 * Progressive withholding from a customer-configured tax table.
 *
 * For every bracket whose `from_amount` is below the income:
 *     tax += fixed_amount + rate% × (min(income, to_amount ?? ∞) − from_amount)
 *
 * `fixed_amount` is therefore an extra flat charge per bracket reached (enter 0
 * when the table is purely rate based). Annual tables are applied to a monthly
 * period by annualising the income (×12) and dividing the annual tax by 12.
 *
 * No jurisdiction's rules are built in: allowances, credits, caps and
 * statutory contributions must be modelled by the customer (tables and pay
 * components); compliance remains the customer's responsibility.
 */
class TaxCalculator
{
    /**
     * Withholding for one monthly period, rounded half-up to 2 decimals.
     */
    public function monthlyTax(string $monthlyTaxable, TaxTable $table): string
    {
        if (Money::compare($monthlyTaxable, '0') <= 0) {
            return Money::round('0', 2);
        }

        $factor = (string) (12 / $table->period->periodsPerYear());
        $income = $table->period === TaxTablePeriod::Monthly ? $monthlyTaxable : Money::mul($monthlyTaxable, $factor);
        $tax = $this->progressive($income, $table->brackets->all());

        if ($table->period !== TaxTablePeriod::Monthly) {
            $tax = Money::div($tax, $factor);
        }

        return Money::round($tax, 2);
    }

    /**
     * @param  array<int, TaxBracket>  $brackets
     */
    public function progressive(string $income, array $brackets): string
    {
        usort($brackets, fn (TaxBracket $a, TaxBracket $b) => Money::compare((string) $a->from_amount, (string) $b->from_amount));

        $tax = Money::zero();

        foreach ($brackets as $bracket) {
            $from = Money::normalize((string) $bracket->from_amount);

            if (Money::compare($income, $from) <= 0) {
                continue;
            }

            $upper = $bracket->to_amount === null ? $income : Money::normalize((string) $bracket->to_amount);
            $top = Money::compare($income, $upper) < 0 ? $income : $upper;
            $portion = Money::sub($top, $from);

            if (Money::compare($portion, '0') > 0) {
                $tax = Money::add($tax, Money::percent($portion, (string) $bracket->rate));
            }

            $tax = Money::add($tax, Money::normalize((string) $bracket->fixed_amount));
        }

        return $tax;
    }
}
