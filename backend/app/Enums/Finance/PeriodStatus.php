<?php

namespace App\Enums\Finance;

enum PeriodStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}
