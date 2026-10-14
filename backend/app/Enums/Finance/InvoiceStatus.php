<?php

namespace App\Enums\Finance;

/**
 * Lifecycle of a customer invoice. `Approved` means issued and posted to the
 * GL. `Overdue` is never stored: it is derived (Approved/PartiallyPaid with a
 * past due date) and only used as a list filter / display label.
 */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Void = 'void';

    /** Statuses whose document is still editable (not yet posted). */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::PendingApproval], true);
    }

    /** Posted and still able to receive payments. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Approved, self::PartiallyPaid], true);
    }

    /** @return array<int, string> statuses that may be stored */
    public static function storable(): array
    {
        return array_values(array_map(
            fn (self $case) => $case->value,
            array_filter(self::cases(), fn (self $case) => $case !== self::Overdue),
        ));
    }
}
