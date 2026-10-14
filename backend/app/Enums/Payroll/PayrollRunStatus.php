<?php

namespace App\Enums\Payroll;

/**
 * Draft → Calculated → Approved → Finalized (posted to the GL) → Paid.
 * Cancelled is reachable from any state before Finalized.
 */
enum PayrollRunStatus: string
{
    case Draft = 'draft';
    case Calculated = 'calculated';
    case Approved = 'approved';
    case Finalized = 'finalized';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function canCalculate(): bool
    {
        return in_array($this, [self::Draft, self::Calculated], true);
    }

    public function canCancel(): bool
    {
        return in_array($this, [self::Draft, self::Calculated, self::Approved], true);
    }

    /** Finalized and paid runs are visible to employees (self-service). */
    public function isReleased(): bool
    {
        return in_array($this, [self::Finalized, self::Paid], true);
    }

    /**
     * @return array<int, string>
     */
    public static function releasedValues(): array
    {
        return [self::Finalized->value, self::Paid->value];
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
