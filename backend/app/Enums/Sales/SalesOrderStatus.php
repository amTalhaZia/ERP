<?php

namespace App\Enums\Sales;

enum SalesOrderStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Approved = 'approved';
    case PartiallyDelivered = 'partially_delivered';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    /** Orders that count as "won business" in reports (approved or further along). */
    public static function approvedOrLater(): array
    {
        return [self::Approved, self::PartiallyDelivered, self::Delivered];
    }

    /** Orders that may receive new deliveries. */
    public function acceptsDeliveries(): bool
    {
        return in_array($this, [self::Approved, self::PartiallyDelivered], true);
    }
}
