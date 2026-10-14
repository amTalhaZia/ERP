<?php

namespace App\Enums\Finance;

enum TaxRateType: string
{
    case Sales = 'sales';
    case Purchase = 'purchase';
    case Both = 'both';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
