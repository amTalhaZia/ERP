<?php

namespace App\Http\Requests\Finance\Receivables;

use App\Enums\Finance\AccountType;
use App\Http\Requests\Finance\Receivables\Rules\TenantReferenceExists;
use App\Models\Finance\Account;
use App\Models\Finance\ExpenseClaim;
use App\Models\Finance\TaxRate;
use App\Support\Tenancy\Rules\AccessibleBranch;
use App\Support\Tenancy\Rules\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseClaimRequest extends FormRequest
{
    /** Permission is checked before validation so a forbidden user gets 403, not 422. */
    public function authorize(): bool
    {
        $claim = $this->route('expenseClaim');

        return $claim instanceof ExpenseClaim
            ? (bool) $this->user()?->can('update', $claim)
            : (bool) $this->user()?->can('create', ExpenseClaim::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'employee_id' => ['nullable', 'integer', new TenantReferenceExists('employees', optionalModule: true)],
            'claim_date' => [$required, 'date_format:Y-m-d'],
            'currency' => ['nullable', 'string', 'size:3', 'alpha'],
            'branch_id' => ['nullable', 'integer', new AccessibleBranch],
            'notes' => ['nullable', 'string', 'max:5000'],
            'lines' => [$required, 'array', 'min:1', 'max:100'],
            // Existing line ids are kept (update in place); ownership is checked by SaveExpenseClaimAction.
            'lines.*.id' => ['nullable', 'integer', 'distinct'],
            'lines.*.expense_date' => ['required', 'date_format:Y-m-d'],
            'lines.*.category' => ['required', 'string', 'max:60'],
            'lines.*.account_id' => ['required', 'integer', new TenantExists(Account::class, where: ['type' => AccountType::Expense->value, 'is_active' => true])],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.amount' => ['required', 'numeric', 'gt:0', StoreInvoiceRequest::AMOUNT],
            'lines.*.tax_rate_id' => ['nullable', 'integer', new TenantExists(TaxRate::class, where: ['is_active' => true])],
        ];
    }
}
