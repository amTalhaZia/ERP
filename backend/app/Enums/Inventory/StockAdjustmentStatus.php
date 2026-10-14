<?php

namespace App\Enums\Inventory;

enum StockAdjustmentStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
}
