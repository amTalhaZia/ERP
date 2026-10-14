<?php

namespace App\Http\Requests\HR;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDesignationRequest extends FormRequest
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
            'title' => [$required, 'string', 'max:150'],
            'code' => [
                $required, 'string', 'max:20', 'alpha_dash',
                Rule::unique('designations', 'code')->where('company_id', $companyId)->ignore($this->route('designation')?->id),
            ],
            'level' => ['sometimes', 'integer', 'between:1,99'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
