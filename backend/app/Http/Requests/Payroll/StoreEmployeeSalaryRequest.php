<?php

namespace App\Http\Requests\Payroll;

use App\Enums\Payroll\PayFrequency;
use App\Models\Payroll\PayComponent;
use App\Models\Payroll\SalaryStructure;
use App\Support\Tenancy\Rules\TenantExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeSalaryRequest extends FormRequest
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
            'salary_structure_id' => ['nullable', 'integer', new TenantExists(SalaryStructure::class)],
            'base_salary' => ['required', 'numeric', 'min:0', StorePayComponentRequest::MONEY],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'effective_from' => [
                'required', 'date_format:Y-m-d',
                Rule::unique('employee_salaries', 'effective_from')->where('employee_id', $this->route('employee')?->id),
            ],
            'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
            'pay_frequency' => ['sometimes', Rule::enum(PayFrequency::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
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

        if (isset($data['components'])) {
            ksort($data['components']);
            $data['components'] = array_values($data['components']);
        }

        return $data;
    }
}
