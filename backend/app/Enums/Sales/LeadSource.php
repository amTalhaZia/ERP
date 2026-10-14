<?php

namespace App\Enums\Sales;

enum LeadSource: string
{
    case Website = 'website';
    case Referral = 'referral';
    case Campaign = 'campaign';
    case ColdCall = 'cold_call';
    case Event = 'event';
    case Other = 'other';
}
