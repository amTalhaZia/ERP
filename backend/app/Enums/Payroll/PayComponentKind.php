<?php

namespace App\Enums\Payroll;

use App\Services\Finance\ChartOfAccountsTemplate;

enum PayComponentKind: string
{
    case Earning = 'earning';
    case Deduction = 'deduction';
    case EmployerContribution = 'employer_contribution';

    /**
     * GL account used when the component has no explicit gl_account_code.
     */
    public function defaultAccountCode(): string
    {
        return match ($this) {
            self::Earning => ChartOfAccountsTemplate::SALARIES,
            self::Deduction => ChartOfAccountsTemplate::ACCRUED_LIABILITIES,
            self::EmployerContribution => ChartOfAccountsTemplate::PAYROLL_TAXES,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
