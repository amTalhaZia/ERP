<?php

namespace App\Enums\Inventory;

enum SerialStatus: string
{
    case InStock = 'in_stock';
    case Issued = 'issued';
    case Returned = 'returned';
    case Scrapped = 'scrapped';
}
