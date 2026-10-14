<?php

namespace App\Enums\Finance;

/**
 * Reporting classification under AccountType. Reports group by subtype and the
 * cash-flow statement maps subtypes to operating / investing / financing.
 */
enum AccountSubtype: string
{
    // Assets
    case Cash = 'cash';
    case Bank = 'bank';
    case Receivable = 'receivable';
    case Inventory = 'inventory';
    case Prepaid = 'prepaid';
    case CurrentAsset = 'current_asset';
    case FixedAsset = 'fixed_asset';
    case AccumulatedDepreciation = 'accumulated_depreciation';
    case Intangible = 'intangible';
    case Investment = 'investment';
    // Liabilities
    case Payable = 'payable';
    case Tax = 'tax';
    case AccruedPayroll = 'accrued_payroll';
    case AccruedLiability = 'accrued_liability';
    case CurrentLiability = 'current_liability';
    case Loan = 'loan';
    case LongTermLiability = 'long_term_liability';
    // Equity
    case ShareCapital = 'share_capital';
    case RetainedEarnings = 'retained_earnings';
    case OwnerDrawings = 'owner_drawings';
    case OtherEquity = 'other_equity';
    // Revenue
    case SalesRevenue = 'sales_revenue';
    case ServiceRevenue = 'service_revenue';
    case OtherIncome = 'other_income';
    // Expenses
    case CostOfSales = 'cost_of_sales';
    case OperatingExpense = 'operating_expense';
    case PayrollExpense = 'payroll_expense';
    case Depreciation = 'depreciation';
    case FinanceCost = 'finance_cost';
    case TaxExpense = 'tax_expense';
    case OtherExpense = 'other_expense';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    /** Cash-flow section for a counter-account (direct method). */
    public static function cashFlowSection(AccountType $type, ?string $subtype): string
    {
        $investing = [self::FixedAsset, self::AccumulatedDepreciation, self::Intangible, self::Investment];
        $financing = [self::Loan, self::LongTermLiability];

        $case = $subtype === null ? null : self::tryFrom($subtype);

        if ($case !== null && in_array($case, $investing, true)) {
            return 'investing';
        }

        if ($type === AccountType::Equity || ($case !== null && in_array($case, $financing, true))) {
            return 'financing';
        }

        return 'operating';
    }
}
