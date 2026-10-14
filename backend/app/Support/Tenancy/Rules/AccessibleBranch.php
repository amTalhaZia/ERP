<?php

namespace App\Support\Tenancy\Rules;

use App\Models\Branch;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Branch must belong to the tenant and be within the user's branch restriction.
 */
class AccessibleBranch implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $tenant = app(TenantContext::class);

        if (! Branch::query()->whereKey($value)->exists() || ! $tenant->canAccessBranch((int) $value)) {
            $fail('The selected :attribute is invalid.');
        }
    }
}
