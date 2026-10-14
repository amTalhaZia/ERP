<?php

namespace App\Http\Requests\Sales\Rules;

use App\Models\CompanyUser;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The referenced user must hold an active membership in the current company
 * (sales reps, lead assignees, opportunity owners).
 */
class CompanyMember implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $companyId = app(TenantContext::class)->id();

        $isMember = $companyId !== null && CompanyUser::query()
            ->where('company_id', $companyId)
            ->where('user_id', $value)
            ->where('is_active', true)
            ->exists();

        if (! $isMember) {
            $fail('The selected :attribute is not a member of this company.');
        }
    }
}
