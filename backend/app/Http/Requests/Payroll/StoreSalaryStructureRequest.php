<?php

namespace App\Http\Requests\Payroll;

use App\Models\Payroll\PayComponent;
use App\Support\Tenancy\Rules\TenantExists;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalaryStructureRequest extends FormRequest
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
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [
                $required, 'string', 'max:100',
                Rule::unique('salary_structures', 'name')->where('company_id', app(TenantContext::class)->id())->ignore($this->route('salary_structure')?->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'currency' => [$required, 'string', 'size:3', 'alpha'],
            'is_active' => ['sometimes', 'boolean'],
            'components' => ['sometimes', 'array', 'max:100'],
            'components.*.pay_component_id' => ['required', 'integer', 'distinct', new TenantExists(PayComponent::class)],
            'components.*.amount' => ['nullable', 'numeric', 'min:0', StorePayComponentRequest::MONEY],
            'components.*.rate' => ['nullable', 'numeric', 'min:0', 'max:1000', StorePayComponentRequest::RATE],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = $this->validated();

        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        if (isset($data['components'])) {
            ksort($data['components']);
            $data['components'] = array_values($data['components']);
        }

        return $data;
    }
}
