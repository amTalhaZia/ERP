<?php

namespace App\Enums\Documents;

enum DocumentCategory: string
{
    case Contract = 'contract';
    case Policy = 'policy';
    case Invoice = 'invoice';
    case Purchase = 'purchase';
    case Employee = 'employee';
    case Legal = 'legal';
    case Certificate = 'certificate';
    case Report = 'report';
    case Other = 'other';
}
