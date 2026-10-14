<?php

namespace App\Enums\HR;

enum AttendanceSource: string
{
    case Manual = 'manual';
    case Import = 'import';
    case Biometric = 'biometric';
    case SelfService = 'self';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
