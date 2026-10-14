<?php

namespace App\Enums\Payroll;

/**
 * The income period a tax table's brackets are expressed in. Annual tables
 * are applied to monthly payroll by annualising (×12) and de-annualising (÷12).
 */
enum TaxTablePeriod: string
{
    case Monthly = 'monthly';
    case Annual = 'annual';

    public function periodsPerYear(): int
    {
        return match ($this) {
            self::Monthly => 12,
            self::Annual => 1,
        };
    }
}
