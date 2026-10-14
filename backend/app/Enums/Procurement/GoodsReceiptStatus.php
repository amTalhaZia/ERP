<?php

namespace App\Enums\Procurement;

enum GoodsReceiptStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
}
