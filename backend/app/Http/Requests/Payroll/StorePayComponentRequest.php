<?php

namespace App\Http\Requests\Payroll;

use App\Enums\Payroll\CalculationMethod;
use App\Enums\Payroll\PayComponentKind;
use App\Models\Finance\Account;
use App\Models\Payroll\PayComponent;
use App\Support\Tenancy\Rules\TenantExists;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePayComponentRequest extends FormRequest
{
    public const MONEY = 'regex:/^\d{1,14}(\.\d{1,4})?$/';

    public const RATE = 'regex:/^\d{1,4}(\.\d{1,4})?$/';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        $component = $this->route('pay_component');

        return [
            'code' => [
                $required, 'string', 'max:30', 'alpha_dash', Rule::notIn(PayComponent::RESERVED_CODES),
                Rule::unique('pay_components', 'code')->where('company_id', app(TenantContext::class)->id())->ignore($component?->id),
            ],
            'name' => [$required, 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'kind' => [$required, Rule::enum(PayComponentKind::class)],
            'calculation' => [$required, Rule::enum(CalculationMethod::class)],
            'default_amount' => ['sometimes', 'numeric', 'min:0', self::MONEY],
            'default_rate' => ['sometimes', 'numeric', 'min:0', 'max:1000', self::RATE],
            'is_taxable' => ['sometimes', 'boolean'],
            'reduces_taxable' => ['sometimes', 'boolean'],
            'affects_gross' => ['sometimes', 'boolean'],
            'gl_account_code' => ['nullable', 'string', 'max:20', new TenantExists(Account::class, 'code', ['is_active' => true])],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $component = $this->route('pay_component');
            $kind = $this->input('kind', $component?->kind?->value);
            $calculation = $this->input('calculation', $component?->calculation?->value);

            if ($kind === PayComponentKind::Earning->value && $calculation === CalculationMethod::PercentOfGross->value) {
                $validator->errors()->add('calculation', 'Earnings cannot be calculated as a percentage of gross.');
            }
        }];
    }
}
