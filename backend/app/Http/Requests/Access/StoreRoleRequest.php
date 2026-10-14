<?php

namespace App\Http\Requests\Access;

use App\Models\Role;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Used for both POST /roles and PUT /roles/{role}. */
class StoreRoleRequest extends FormRequest
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
        $companyId = app(TenantContext::class)->id();
        $current = $this->route('role');
        $currentId = $current instanceof Role ? $current->id : (is_numeric($current) ? (int) $current : null);

        return [
            'name' => [
                $this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:100',
                Rule::unique('roles', 'name')->where('company_id', $companyId)->ignore($currentId),
            ],
            'description' => ['nullable', 'string', 'max:300'],
            'permission_ids' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'array'],
            'permission_ids.*' => ['integer', 'distinct', Rule::exists('permissions', 'id')->where('is_platform', false)],
        ];
    }
}
