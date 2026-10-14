<?php

namespace App\Enums\Procurement;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    /** Statuses that count as committed spend. */
    public static function committed(): array
    {
        return [self::Approved, self::PartiallyReceived, self::Received, self::Closed];
    }

    /** @return array<int, string> */
    public static function committedValues(): array
    {
        return array_map(fn (self $status) => $status->value, self::committed());
    }

    public function canReceive(): bool
    {
        return in_array($this, [self::Approved, self::PartiallyReceived], true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Closed, self::Cancelled], true);
    }
}
