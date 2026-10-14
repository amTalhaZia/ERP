<?php

namespace App\Http\Requests\Access;

use App\Http\Requests\Access\Rules\TenantRole;
use App\Models\Branch;
use App\Support\Tenancy\Rules\TenantExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Membership-level update. At least one of the three fields must be present;
 * `branch_ids: null` explicitly clears the branch restriction.
 */
class UpdateCompanyUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'role_ids' => ['sometimes', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'distinct', new TenantRole],
            'branch_ids' => ['sometimes', 'nullable', 'array'],
            'branch_ids.*' => ['integer', 'distinct', new TenantExists(Branch::class)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->hasAny(['role_ids', 'branch_ids', 'is_active'])) {
                    $validator->errors()->add('role_ids', 'Provide at least one of role_ids, branch_ids or is_active.');
                }
            },
        ];
    }
}
