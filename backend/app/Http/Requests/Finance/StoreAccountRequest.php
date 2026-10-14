<?php

namespace App\Http\Requests\Finance;

use App\Enums\Finance\AccountSubtype;
use App\Enums\Finance\AccountType;
use App\Models\Finance\Account;
use App\Support\Tenancy\Rules\TenantExists;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends FormRequest
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
        $accountId = $this->route('account')?->id;
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'code' => [
                $required, 'string', 'max:20', 'alpha_dash',
                Rule::unique('accounts', 'code')->where('company_id', $companyId)->ignore($accountId),
            ],
            'name' => [$required, 'string', 'max:150'],
            'type' => [$required, Rule::enum(AccountType::class)],
            'subtype' => ['nullable', 'string', Rule::in(AccountSubtype::values())],
            'parent_id' => ['nullable', 'integer', Rule::notIn([$accountId ?? 0]), new TenantExists(Account::class)],
            'is_bank' => ['sometimes', 'boolean'],
            'is_cash' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'currency' => ['nullable', 'string', 'size:3', 'alpha'],
            'description' => ['nullable', 'string', 'max:500'],
            'opening_balance' => ['nullable', 'numeric', 'regex:/^-?\d{1,14}(\.\d{1,4})?$/'],
            'opening_balance_date' => ['nullable', 'date'],
        ];
    }
}
