<?php

namespace App\Enums\Finance;

enum PaymentStatus: string
{
    case Posted = 'posted';
    case Void = 'void';
}
