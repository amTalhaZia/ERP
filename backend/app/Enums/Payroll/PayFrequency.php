<?php

namespace App\Enums\Payroll;

/**
 * Only monthly payroll is supported in this release.
 */
enum PayFrequency: string
{
    case Monthly = 'monthly';
}
