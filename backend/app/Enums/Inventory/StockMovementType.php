<?php

namespace App\Enums\Inventory;

/**
 * Direction is derived from the type, never from the sign the caller passes:
 * inbound types store a positive quantity, outbound types a negative one.
 */
enum StockMovementType: string
{
    case Receipt = 'receipt';
    case Issue = 'issue';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case Sale = 'sale';
    case Purchase = 'purchase';
    case ReturnIn = 'return_in';
    case ReturnOut = 'return_out';
    case Opening = 'opening';

    public function isInbound(): bool
    {
        return match ($this) {
            self::Receipt, self::TransferIn, self::AdjustmentIn, self::Purchase, self::ReturnIn, self::Opening => true,
            self::Issue, self::TransferOut, self::AdjustmentOut, self::Sale, self::ReturnOut => false,
        };
    }

    public function isOutbound(): bool
    {
        return ! $this->isInbound();
    }

    /** Status a serial number takes after a movement of this type. */
    public function serialStatus(): SerialStatus
    {
        return match ($this) {
            self::ReturnOut => SerialStatus::Returned,
            self::AdjustmentOut => SerialStatus::Scrapped,
            self::Issue, self::TransferOut, self::Sale => SerialStatus::Issued,
            default => SerialStatus::InStock,
        };
    }
}
