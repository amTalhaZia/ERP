<?php

namespace App\Enums\Procurement;

enum PurchaseReturnReason: string
{
    case Damaged = 'damaged';
    case WrongItem = 'wrong_item';
    case Quality = 'quality';
    case Excess = 'excess';
    case Other = 'other';
}
