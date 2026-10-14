<?php

namespace App\Http\Requests\Payroll;

use App\Models\Finance\BankAccount;
use App\Support\Tenancy\Rules\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

class MarkPayrollPaidRequest extends FormRequest
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
            'bank_account_id' => ['required', 'integer', new TenantExists(BankAccount::class, where: ['is_active' => true])],
            'paid_at' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
