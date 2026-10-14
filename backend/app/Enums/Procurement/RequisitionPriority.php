<?php

namespace App\Enums\Procurement;

enum RequisitionPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';
}
