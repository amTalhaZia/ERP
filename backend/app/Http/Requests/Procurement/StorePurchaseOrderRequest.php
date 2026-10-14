<?php

namespace App\Http\Requests\Procurement;

use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Procurement\PurchaseRequisition;
use App\Models\Procurement\Supplier;
use App\Support\Tenancy\Rules\AccessibleBranch;
use App\Support\Tenancy\Rules\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Lines are the full desired set on both create and update (wholesale replace).
 * product_id / warehouse_id must belong to the current tenant (TenantExists).
 */
class StorePurchaseOrderRequest extends FormRequest
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
            'supplier_id' => [$required, 'integer', new TenantExists(Supplier::class)],
            'requisition_id' => ['nullable', 'integer', new TenantExists(PurchaseRequisition::class)],
            'branch_id' => ['nullable', 'integer', new AccessibleBranch],
            'order_date' => [$required, 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'currency' => ['nullable', 'string', 'size:3', 'alpha'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'warehouse_id' => ['nullable', 'integer', new TenantExists(Warehouse::class)],
            'delivery_address' => ['nullable', 'string', 'max:500'],
            'payment_terms_days' => ['nullable', 'integer', 'between:0,365'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.product_id' => ['nullable', 'integer', new TenantExists(Product::class)],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.uom' => ['nullable', 'string', 'max:20'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('currency'))) {
            $this->merge(['currency' => strtoupper($this->input('currency'))]);
        }
    }
}
