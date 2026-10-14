<?php

namespace App\Http\Requests\Finance\Receivables;

use App\Enums\Finance\PaymentMethod;
use App\Enums\Finance\PaymentPartnerType;
use App\Models\Finance\BankAccount;
use App\Models\Finance\Payment;
use App\Services\Finance\Receivables\PartnerDirectory;
use App\Support\Tenancy\Rules\AccessibleBranch;
use App\Support\Tenancy\Rules\TenantExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Receipt / payment. `type` is optional and must match the partner type
 * (customer → receipt, supplier/employee → payment). Allocations must sum to
 * the amount exactly (checked again, with balances, inside the action).
 */
class StorePaymentRequest extends FormRequest
{
    /** Permission is checked before validation so a forbidden user gets 403, not 422. */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Payment::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::in(['receipt', 'payment'])],
            'partner_type' => ['required', Rule::enum(PaymentPartnerType::class)],
            'partner_id' => ['required', 'integer'],
            'payment_date' => ['required', 'date_format:Y-m-d'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'bank_account_id' => ['required', 'integer', new TenantExists(BankAccount::class, where: ['is_active' => true])],
            'amount' => ['required', 'numeric', 'gt:0', StoreInvoiceRequest::AMOUNT],
            'currency' => ['nullable', 'string', 'size:3', 'alpha'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'branch_id' => ['nullable', 'integer', new AccessibleBranch],
            'allocations' => ['required', 'array', 'min:1', 'max:100'],
            'allocations.*.type' => ['required', Rule::in(['invoice', 'bill', 'expense_claim'])],
            'allocations.*.id' => ['required', 'integer', 'distinct'],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0', StoreInvoiceRequest::AMOUNT],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['partner_type', 'partner_id'])) {
                    return;
                }

                $partnerType = PaymentPartnerType::from((string) $this->input('partner_type'));

                if (! app(PartnerDirectory::class)->partnerExists($partnerType, (int) $this->input('partner_id'))) {
                    $validator->errors()->add('partner_id', 'The selected partner is invalid.');
                }

                $type = $this->input('type');
                if ($type !== null && $type !== $partnerType->paymentType()->value) {
                    $validator->errors()->add('type', "A {$partnerType->value} transaction must be a {$partnerType->paymentType()->value}.");
                }
            },
        ];
    }
}
