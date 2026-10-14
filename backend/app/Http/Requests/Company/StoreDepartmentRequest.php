<?php

namespace App\Http\Requests\Company;

use App\Models\Branch;
use App\Models\Department;
use App\Support\Tenancy\Rules\TenantExists;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
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
        $current = $this->route('department');

        return [
            'name' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:150'],
            'code' => [
                $this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:20', 'alpha_dash',
                Rule::unique('departments', 'code')->where('company_id', $companyId)->ignore($current?->id)->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'branch_id' => ['nullable', 'integer', new TenantExists(Branch::class)],
            'parent_id' => [
                'nullable', 'integer', new TenantExists(Department::class),
                function (string $attribute, mixed $value, Closure $fail) use ($current): void {
                    if ($current && (int) $value === $current->id) {
                        $fail('A department cannot be its own parent.');
                    }
                },
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
