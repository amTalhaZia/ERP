<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\Account;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreBankAccountRequest extends FormRequest
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
            'account_id' => [$required, 'integer', $this->cashOrBankAccount()],
            'name' => [$required, 'string', 'max:150'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'account_number' => ['nullable', 'string', 'max:64'],
            'iban' => ['nullable', 'string', 'max:34', 'alpha_num'],
            'swift' => ['nullable', 'string', 'max:20', 'alpha_num'],
            'currency' => ['nullable', 'string', 'size:3', 'alpha'],
            'opening_balance' => ['nullable', 'numeric', 'regex:/^-?\d{1,14}(\.\d{1,4})?$/'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** The GL account must belong to the tenant and be flagged is_bank or is_cash. */
    private function cashOrBankAccount(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! app(TenantContext::class)->has()) {
                $fail('The selected :attribute is invalid.');

                return;
            }

            $ok = Account::query()->whereKey($value)
                ->where(fn ($q) => $q->where('is_bank', true)->orWhere('is_cash', true))
                ->exists();

            if (! $ok) {
                $fail('The selected :attribute must be a cash or bank GL account of this company.');
            }
        };
    }
}
