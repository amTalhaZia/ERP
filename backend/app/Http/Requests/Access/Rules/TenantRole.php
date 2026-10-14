<?php

namespace App\Http\Requests\Access\Rules;

use App\Models\Role;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The referenced role must be a company role of the *current* tenant. Platform
 * roles and roles of other companies are simply "invalid", so nothing about
 * them is disclosed.
 */
class TenantRole implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $companyId = app(TenantContext::class)->id();

        if ($companyId === null || ! is_numeric($value) || ! Role::forTenant($companyId)->whereKey((int) $value)->exists()) {
            $fail('The selected :attribute is invalid.');
        }
    }
}
