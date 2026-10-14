<?php

namespace App\Http\Requests\Finance;

use App\Enums\Finance\TaxRateType;
use App\Models\Finance\Account;
use App\Support\Tenancy\Rules\TenantExists;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaxRateRequest extends FormRequest
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
        $taxRateId = $this->route('tax_rate')?->id;
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:100'],
            'code' => [
                $required, 'string', 'max:20', 'alpha_dash',
                Rule::unique('tax_rates', 'code')->where('company_id', $companyId)->ignore($taxRateId),
            ],
            'rate' => [$required, 'numeric', 'min:0', 'max:1000', 'regex:/^\d{1,4}(\.\d{1,4})?$/'],
            'type' => [$required, Rule::enum(TaxRateType::class)],
            'account_id' => ['nullable', 'integer', new TenantExists(Account::class)],
            'is_compound' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
