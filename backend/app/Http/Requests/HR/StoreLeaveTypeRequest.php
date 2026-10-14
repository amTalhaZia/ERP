<?php

namespace App\Http\Requests\HR;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaveTypeRequest extends FormRequest
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
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:100'],
            'code' => [
                $required, 'string', 'max:20', 'alpha_dash',
                Rule::unique('leave_types', 'code')->where('company_id', $companyId)->ignore($this->route('leave_type')?->id),
            ],
            'days_per_year' => [$required, 'numeric', 'between:0,999.9'],
            'is_paid' => ['sometimes', 'boolean'],
            'requires_approval' => ['sometimes', 'boolean'],
            'carry_forward' => ['sometimes', 'boolean'],
            'max_carry_forward_days' => ['nullable', 'numeric', 'between:0,999.9'],
            'color' => ['nullable', 'string', 'max:9', 'regex:/^#[0-9a-fA-F]{6}([0-9a-fA-F]{2})?$/'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
