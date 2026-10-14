<?php

namespace App\Http\Requests\Finance\Receivables;

use App\Enums\Finance\AccountType;
use App\Http\Requests\Finance\Receivables\Rules\TenantReferenceExists;
use App\Models\Finance\Account;
use App\Models\Finance\Invoice;
use App\Models\Finance\TaxRate;
use App\Models\Inventory\Product;
use App\Support\Tenancy\Rules\AccessibleBranch;
use App\Support\Tenancy\Rules\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Draft invoice create (POST) / update (PUT/PATCH, partial; `lines` replaces
 * all lines). Amounts are inputs only — totals are computed server-side.
 */
class StoreInvoiceRequest extends FormRequest
{
    public const AMOUNT = 'regex:/^\d{1,14}(\.\d{1,4})?$/';

    /** Permission is checked before validation so a forbidden user gets 403, not 422. */
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        return $invoice instanceof Invoice
            ? (bool) $this->user()?->can('update', $invoice)
            : (bool) $this->user()?->can('create', Invoice::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'customer_id' => [$required, 'integer', new TenantReferenceExists('customers')],
            'sales_order_id' => ['nullable', 'integer', new TenantReferenceExists('sales_orders', optionalModule: true)],
            'invoice_date' => [$required, 'date_format:Y-m-d'],
            'due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:invoice_date'],
            'currency' => ['nullable', 'string', 'size:3', 'alpha'],
            'branch_id' => ['nullable', 'integer', new AccessibleBranch],
            'notes' => ['nullable', 'string', 'max:5000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            ...self::lineRules($required, [AccountType::Revenue->value]),
        ];
    }

    /**
     * Line rules shared with bills.
     *
     * @param  array<int, string>  $accountTypes
     * @return array<string, array<int, mixed>>
     */
    public static function lineRules(string $required, array $accountTypes): array
    {
        return [
            'lines' => [$required, 'array', 'min:1', 'max:200'],
            'lines.*.product_id' => ['nullable', 'integer', new TenantExists(Product::class)],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', self::AMOUNT],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0', self::AMOUNT],
            'lines.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.tax_rate_id' => ['nullable', 'integer', new TenantExists(TaxRate::class, where: ['is_active' => true])],
            'lines.*.account_id' => [
                'nullable', 'integer', new TenantExists(Account::class, where: ['is_active' => true]),
                function (string $attribute, mixed $value, \Closure $fail) use ($accountTypes): void {
                    if ($value !== null && ! Account::query()->whereKey($value)->whereIn('type', $accountTypes)->exists()) {
                        $fail('The :attribute must be a '.implode(' or ', $accountTypes).' account.');
                    }
                },
            ],
        ];
    }
}
