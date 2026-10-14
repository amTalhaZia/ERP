<?php

namespace App\Enums\Payroll;

/**
 * How a pay component amount is derived. Formulas are deliberately not
 * supported; percentages are expressed as e.g. 7.5 for 7.5 %.
 */
enum CalculationMethod: string
{
    case Fixed = 'fixed';
    case PercentOfBase = 'percent_of_base';
    case PercentOfGross = 'percent_of_gross';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
