<?php

namespace App\Http\Requests\Finance\Receivables;

use App\Enums\Finance\AccountType;
use App\Http\Requests\Finance\Receivables\Rules\TenantReferenceExists;
use App\Models\Finance\Bill;
use App\Support\Tenancy\Rules\AccessibleBranch;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Draft supplier bill create / update. supplier_reference (the supplier's own
 * invoice number) is unique per supplier within the company. Line accounts
 * may be expense or asset (inventory, prepaid, fixed asset) accounts.
 */
class StoreBillRequest extends FormRequest
{
    /** Permission is checked before validation so a forbidden user gets 403, not 422. */
    public function authorize(): bool
    {
        $bill = $this->route('bill');

        return $bill instanceof Bill
            ? (bool) $this->user()?->can('update', $bill)
            : (bool) $this->user()?->can('create', Bill::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        /** @var Bill|null $bill */
        $bill = $this->route('bill');
        $supplierId = $this->input('supplier_id', $bill?->supplier_id);

        return [
            'supplier_id' => [$required, 'integer', new TenantReferenceExists('suppliers')],
            'purchase_order_id' => ['nullable', 'integer', new TenantReferenceExists('purchase_orders', optionalModule: true)],
            'supplier_reference' => [
                $required, 'string', 'max:60',
                Rule::unique('bills', 'supplier_reference')
                    ->where('company_id', app(TenantContext::class)->id())
                    ->where('supplier_id', is_numeric($supplierId) ? (int) $supplierId : 0)
                    ->ignore($bill?->id),
            ],
            'bill_date' => [$required, 'date_format:Y-m-d'],
            'due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:bill_date'],
            'currency' => ['nullable', 'string', 'size:3', 'alpha'],
            'branch_id' => ['nullable', 'integer', new AccessibleBranch],
            'notes' => ['nullable', 'string', 'max:5000'],
            ...StoreInvoiceRequest::lineRules($required, [AccountType::Expense->value, AccountType::Asset->value]),
        ];
    }
}
