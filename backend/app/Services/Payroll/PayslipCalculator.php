<?php

namespace App\Services\Payroll;

use App\Enums\Payroll\CalculationMethod;
use App\Enums\Payroll\PayComponentKind;
use App\Models\Payroll\PayComponent;
use App\Models\Payroll\TaxTable;
use App\Services\Finance\ChartOfAccountsTemplate;
use App\Support\Money\Money;

/**
 * Deterministic monthly payslip calculation (no I/O). Every line amount is
 * rounded half-up to 2 decimals and all totals are sums of rounded lines, so
 * the GL posting built from the lines always balances.
 *
 *  1. earned_base = base × paid_days / working_days (prorated, unpaid leave).
 *  2. Earnings: Fixed = amount; PercentOfBase = rate% × earned_base.
 *     (Fixed amounts are not prorated; PercentOfGross is not valid for earnings.)
 *  3. gross = earned_base + Σ earnings with affects_gross.
 *     Earnings with affects_gross = false (e.g. reimbursements) are paid in
 *     net but excluded from gross and from the PercentOfGross base.
 *  4. Deductions & employer contributions: Fixed / % of earned_base / % of gross.
 *  5. taxable = earned_base + Σ taxable earnings − Σ deductions with
 *     reduces_taxable (pre-tax deductions), floored at 0.
 *  6. tax = progressive brackets of the active tax table (0 + warning when none).
 *  7. total_deductions = Σ deductions + tax; net = gross + non-gross earnings − total_deductions.
 */
class PayslipCalculator
{
    public const WARNING_NO_TAX_TABLE = 'no_tax_table';

    public const WARNING_NO_WORKING_DAYS = 'no_working_days';

    public const WARNING_NEGATIVE_NET = 'negative_net';

    public function __construct(private readonly TaxCalculator $tax) {}

    /**
     * @param  list<array{component: PayComponent, amount: string, rate: string}>  $components
     * @return array{earned_base: string, gross: string, taxable: string, tax: string, total_deductions: string, employer_contributions: string, net: string, warnings: list<string>, lines: list<array<string, mixed>>}
     */
    public function calculate(string $baseSalary, string $workingDays, string $paidDays, array $components, ?TaxTable $taxTable): array
    {
        $warnings = [];
        $base = Money::normalize($baseSalary);

        if (bccomp($workingDays, '0', 1) <= 0) {
            $warnings[] = self::WARNING_NO_WORKING_DAYS;
            $earnedBase = Money::normalize(Money::round($base, 2));
        } elseif (bccomp($paidDays, $workingDays, 1) >= 0) {
            $earnedBase = Money::normalize(Money::round($base, 2));
        } else {
            $earnedBase = Money::normalize(Money::round(Money::div(Money::mul($base, $paidDays), $workingDays), 2));
        }

        $lines = [[
            'pay_component_id' => null, 'code' => 'BASE', 'name' => 'Basic salary', 'kind' => PayComponentKind::Earning->value,
            'calculation' => null, 'rate' => null, 'amount' => $earnedBase, 'is_taxable' => true, 'affects_gross' => true,
            'gl_account_code' => ChartOfAccountsTemplate::SALARIES,
        ]];

        $byKind = fn (PayComponentKind $kind) => array_values(array_filter($components, fn (array $c) => $c['component']->kind === $kind));

        // Earnings
        $gross = $earnedBase;
        $nonGross = Money::zero();
        $taxable = $earnedBase;

        foreach ($byKind(PayComponentKind::Earning) as $entry) {
            $amount = $this->amount($entry, $earnedBase, $earnedBase);
            $component = $entry['component'];

            if ($component->affects_gross) {
                $gross = Money::add($gross, $amount);
            } else {
                $nonGross = Money::add($nonGross, $amount);
            }

            if ($component->is_taxable) {
                $taxable = Money::add($taxable, $amount);
            }

            $lines[] = $this->line($entry, $amount);
        }

        // Deductions
        $deductions = Money::zero();

        foreach ($byKind(PayComponentKind::Deduction) as $entry) {
            $amount = $this->amount($entry, $earnedBase, $gross);
            $deductions = Money::add($deductions, $amount);

            if ($entry['component']->reduces_taxable) {
                $taxable = Money::sub($taxable, $amount);
            }

            $lines[] = $this->line($entry, $amount);
        }

        // Employer contributions (cost to the company, not deducted from pay)
        $employer = Money::zero();

        foreach ($byKind(PayComponentKind::EmployerContribution) as $entry) {
            $amount = $this->amount($entry, $earnedBase, $gross);
            $employer = Money::add($employer, $amount);
            $lines[] = $this->line($entry, $amount);
        }

        if (Money::isNegative($taxable)) {
            $taxable = Money::zero();
        }

        if ($taxTable === null) {
            $warnings[] = self::WARNING_NO_TAX_TABLE;
            $tax = Money::zero();
        } else {
            $tax = Money::normalize($this->tax->monthlyTax($taxable, $taxTable));
        }

        if (! Money::isZero($tax)) {
            $lines[] = [
                'pay_component_id' => null, 'code' => 'TAX', 'name' => 'Income tax withholding', 'kind' => PayComponentKind::Deduction->value,
                'calculation' => null, 'rate' => null, 'amount' => $tax, 'is_taxable' => false, 'affects_gross' => false,
                'gl_account_code' => ChartOfAccountsTemplate::TAX_PAYABLE,
            ];
        }

        $totalDeductions = Money::add($deductions, $tax);
        $net = Money::sub(Money::add($gross, $nonGross), $totalDeductions);

        if (Money::isNegative($net)) {
            $warnings[] = self::WARNING_NEGATIVE_NET;
        }

        $lines = array_values(array_filter($lines, fn (array $line) => ! Money::isZero($line['amount']) || $line['code'] === 'BASE'));

        foreach ($lines as $i => &$line) {
            $line['sort_order'] = $i + 1;
        }
        unset($line);

        return [
            'earned_base' => $earnedBase,
            'gross' => $gross,
            'taxable' => $taxable,
            'tax' => $tax,
            'total_deductions' => $totalDeductions,
            'employer_contributions' => $employer,
            'net' => $net,
            'warnings' => $warnings,
            'lines' => $lines,
        ];
    }

    /**
     * @param  array{component: PayComponent, amount: string, rate: string}  $entry
     */
    private function amount(array $entry, string $earnedBase, string $gross): string
    {
        $amount = match ($entry['component']->calculation) {
            CalculationMethod::Fixed => Money::normalize($entry['amount']),
            CalculationMethod::PercentOfBase => Money::percent($earnedBase, $entry['rate']),
            CalculationMethod::PercentOfGross => Money::percent($gross, $entry['rate']),
        };

        return Money::normalize(Money::round($amount, 2));
    }

    /**
     * @param  array{component: PayComponent, amount: string, rate: string}  $entry
     * @return array<string, mixed>
     */
    private function line(array $entry, string $amount): array
    {
        $component = $entry['component'];
        $isPercent = $component->calculation !== CalculationMethod::Fixed;

        return [
            'pay_component_id' => $component->id,
            'code' => $component->code,
            'name' => $component->name,
            'kind' => $component->kind->value,
            'calculation' => $component->calculation->value,
            'rate' => $isPercent ? Money::normalize($entry['rate']) : null,
            'amount' => $amount,
            'is_taxable' => $component->kind === PayComponentKind::Earning && $component->is_taxable,
            'affects_gross' => $component->kind === PayComponentKind::Earning && $component->affects_gross,
            'gl_account_code' => $component->accountCode(),
        ];
    }
}
