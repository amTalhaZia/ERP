<?php

namespace App\Enums\Procurement;

enum PurchaseReturnStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
}
