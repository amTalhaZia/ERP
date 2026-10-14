<?php

namespace App\Enums\Sales;

enum CommunicationType: string
{
    case Call = 'call';
    case Email = 'email';
    case Meeting = 'meeting';
    case Note = 'note';
}
