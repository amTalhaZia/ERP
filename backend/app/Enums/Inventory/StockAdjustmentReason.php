<?php

namespace App\Enums\Inventory;

enum StockAdjustmentReason: string
{
    case Count = 'count';
    case Damage = 'damage';
    case Loss = 'loss';
    case Correction = 'correction';
    case Expiry = 'expiry';
}
