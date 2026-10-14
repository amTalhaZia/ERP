<?php

namespace App\Support\Tenancy;

use RuntimeException;

class TenantMismatchException extends RuntimeException
{
    public function __construct(string $model, int $given, ?int $expected)
    {
        parent::__construct(sprintf(
            'Refusing to persist %s for company %d while company %s is active.',
            class_basename($model),
            $given,
            $expected === null ? 'none' : (string) $expected,
        ));
    }
}
