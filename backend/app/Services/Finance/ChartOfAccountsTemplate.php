<?php

namespace App\Services\Finance;

use App\Enums\Finance\AccountSubtype;
use App\Enums\Finance\AccountType;

/**
 * Standard per-company chart of accounts. Sub-ledgers reference these by code
 * (`LedgerPoster` accepts `account_code`), so the codes are stable constants.
 */
final class ChartOfAccountsTemplate
{
    public const CASH = '1000';

    public const BANK = '1010';

    public const ACCOUNTS_RECEIVABLE = '1100';

    public const INVENTORY = '1200';

    public const PREPAID_EXPENSES = '1300';

    public const FIXED_ASSETS = '1500';

    public const ACCUMULATED_DEPRECIATION = '1510';

    public const ACCOUNTS_PAYABLE = '2000';

    public const TAX_PAYABLE = '2100';

    public const ACCRUED_PAYROLL = '2200';

    public const ACCRUED_LIABILITIES = '2300';

    public const SHARE_CAPITAL = '3000';

    public const RETAINED_EARNINGS = '3100';

    public const OWNER_DRAWINGS = '3200';

    public const SALES_REVENUE = '4000';

    public const SERVICE_REVENUE = '4100';

    public const OTHER_INCOME = '4900';

    public const COST_OF_SALES = '5000';

    public const INVENTORY_ADJUSTMENTS = '5100';

    public const SALARIES = '6000';

    public const PAYROLL_TAXES = '6010';

    public const RENT = '6100';

    public const UTILITIES = '6200';

    public const OFFICE_SUPPLIES = '6300';

    public const MARKETING = '6400';

    public const TRAVEL = '6500';

    public const DEPRECIATION = '6600';

    public const INSURANCE = '6700';

    public const PROFESSIONAL_FEES = '6800';

    public const BANK_CHARGES = '6900';

    public const INTEREST_EXPENSE = '7000';

    public const TAX_EXPENSE = '7100';

    public const MISCELLANEOUS = '7900';

    /**
     * @return list<array{code: string, name: string, type: AccountType, subtype: AccountSubtype, is_cash?: bool, is_bank?: bool}>
     */
    public static function accounts(): array
    {
        return [
            ['code' => self::CASH, 'name' => 'Cash', 'type' => AccountType::Asset, 'subtype' => AccountSubtype::Cash, 'is_cash' => true],
            ['code' => self::BANK, 'name' => 'Bank', 'type' => AccountType::Asset, 'subtype' => AccountSubtype::Bank, 'is_bank' => true],
            ['code' => self::ACCOUNTS_RECEIVABLE, 'name' => 'Accounts Receivable', 'type' => AccountType::Asset, 'subtype' => AccountSubtype::Receivable],
            ['code' => self::INVENTORY, 'name' => 'Inventory', 'type' => AccountType::Asset, 'subtype' => AccountSubtype::Inventory],
            ['code' => self::PREPAID_EXPENSES, 'name' => 'Prepaid Expenses', 'type' => AccountType::Asset, 'subtype' => AccountSubtype::Prepaid],
            ['code' => self::FIXED_ASSETS, 'name' => 'Fixed Assets', 'type' => AccountType::Asset, 'subtype' => AccountSubtype::FixedAsset],
            ['code' => self::ACCUMULATED_DEPRECIATION, 'name' => 'Accumulated Depreciation', 'type' => AccountType::Asset, 'subtype' => AccountSubtype::AccumulatedDepreciation],

            ['code' => self::ACCOUNTS_PAYABLE, 'name' => 'Accounts Payable', 'type' => AccountType::Liability, 'subtype' => AccountSubtype::Payable],
            ['code' => self::TAX_PAYABLE, 'name' => 'Tax Payable', 'type' => AccountType::Liability, 'subtype' => AccountSubtype::Tax],
            ['code' => self::ACCRUED_PAYROLL, 'name' => 'Accrued Payroll', 'type' => AccountType::Liability, 'subtype' => AccountSubtype::AccruedPayroll],
            ['code' => self::ACCRUED_LIABILITIES, 'name' => 'Accrued Liabilities', 'type' => AccountType::Liability, 'subtype' => AccountSubtype::AccruedLiability],

            ['code' => self::SHARE_CAPITAL, 'name' => 'Share Capital', 'type' => AccountType::Equity, 'subtype' => AccountSubtype::ShareCapital],
            ['code' => self::RETAINED_EARNINGS, 'name' => 'Retained Earnings', 'type' => AccountType::Equity, 'subtype' => AccountSubtype::RetainedEarnings],
            ['code' => self::OWNER_DRAWINGS, 'name' => 'Owner Drawings', 'type' => AccountType::Equity, 'subtype' => AccountSubtype::OwnerDrawings],

            ['code' => self::SALES_REVENUE, 'name' => 'Sales Revenue', 'type' => AccountType::Revenue, 'subtype' => AccountSubtype::SalesRevenue],
            ['code' => self::SERVICE_REVENUE, 'name' => 'Service Revenue', 'type' => AccountType::Revenue, 'subtype' => AccountSubtype::ServiceRevenue],
            ['code' => self::OTHER_INCOME, 'name' => 'Other Income', 'type' => AccountType::Revenue, 'subtype' => AccountSubtype::OtherIncome],

            ['code' => self::COST_OF_SALES, 'name' => 'Cost of Sales', 'type' => AccountType::Expense, 'subtype' => AccountSubtype::CostOfSales],
            ['code' => self::INVENTORY_ADJUSTMENTS, 'name' => 'Inventory Adjustments', 'type' => AccountType::Expense, 'subtype' => AccountSubtype::CostOfSales],
            ['code' => self::SALARIES, 'name' => 'Salaries & Wages', 'type' => AccountType::Expense, 'subtype' => AccountSubtype::PayrollExpense],
            ['code' => self::PAYROLL_TAXES, 'name' => 'Payroll Taxes', 'type' => AccountType::Expense, 'subtype' => AccountSubtype::PayrollExpense],
            ['code' => self::RENT, 'name' => 'Rent', 'type' => AccountType::Expense, 'subtype' => AccountSubtype::OperatingExpense],
            ['code' => self::UTILITIES, 'name' => 'Utilities', 'type' => AccountType::Expense, 'subtype' => AccountSubtype::OperatingExpense],
            ['code' => self::OFFICE_SUPPLIES, 'name' => 'Office Supplies', 'type' => AccountType::Expense, 'subtype' => AccountSubtype::OperatingExpense],
            ['code' => self::MARKETING, 'name' => 'Marketing & Advertising', 'type' => AccountType::Expense, 'subtype' => AccountSubtype::OperatingExpense],
            ['code' => self::TRAVEL, 'name' => 'Travel & Entertainment', 'type' => AccountType::Expense, 'subtype' => AccountSubtype::OperatingExpense],
            ['code' => self::DEPRECIATION, 'name' => 'Depreciation Expense', 'type' => AccountType::Expense, 'subtype' => AccountSubtype::Depreciation],
            ['code' => self::INSURANCE, 'name' => 'Insurance', 'type' => AccountType::Expense, 'subtype' => AccountSubtype::OperatingExpense],
            ['code' => self::PROFESSIONAL_FEES, 'name' => 'Professional Fees', 'type' => AccountType::Expense, 'subtype' => AccountSubtype::OperatingExpense],
            ['code' => self::BANK_CHARGES, 'name' => 'Bank Charges', 'type' => AccountType::Expense, 'subtype' => AccountSubtype::FinanceCost],
            ['code' => self::INTEREST_EXPENSE, 'name' => 'Interest Expense', 'type' => AccountType::Expense, 'subtype' => AccountSubtype::FinanceCost],
            ['code' => self::TAX_EXPENSE, 'name' => 'Income Tax Expense', 'type' => AccountType::Expense, 'subtype' => AccountSubtype::TaxExpense],
            ['code' => self::MISCELLANEOUS, 'name' => 'Miscellaneous Expense', 'type' => AccountType::Expense, 'subtype' => AccountSubtype::OtherExpense],
        ];
    }

    /**
     * @return list<array{code: string, name: string, rate: string, type: string, account_code: ?string}>
     */
    public static function taxRates(): array
    {
        return [
            ['code' => 'STD', 'name' => 'Standard Rate', 'rate' => '15.0000', 'type' => 'both', 'account_code' => self::TAX_PAYABLE],
            ['code' => 'ZERO', 'name' => 'Zero Rate', 'rate' => '0.0000', 'type' => 'both', 'account_code' => null],
        ];
    }
}
