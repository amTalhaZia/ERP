<?php

namespace App\Enums\HR;

enum DocumentCategory: string
{
    case Contract = 'contract';
    case Id = 'id';
    case Certificate = 'certificate';
    case Visa = 'visa';
    case Other = 'other';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
