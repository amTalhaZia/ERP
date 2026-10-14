<?php

namespace App\Enums\Sales;

enum QuotationStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Converted = 'converted';

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }
}
