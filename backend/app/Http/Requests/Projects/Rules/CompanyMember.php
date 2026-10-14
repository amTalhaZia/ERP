<?php

namespace App\Http\Requests\Projects\Rules;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * The referenced user must hold an ACTIVE membership in the current company.
 * Users of other companies are reported exactly like unknown ids.
 */
class CompanyMember implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $exists = is_numeric($value) && DB::table('company_user')
            ->where('company_id', app(TenantContext::class)->id())
            ->where('user_id', (int) $value)
            ->where('is_active', true)
            ->exists();

        if (! $exists) {
            $fail('The selected :attribute is not a member of this company.');
        }
    }
}
