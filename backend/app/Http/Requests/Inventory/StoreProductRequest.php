<?php

namespace App\Http\Requests\Inventory;

use App\Enums\Inventory\ProductType;
use App\Models\Inventory\ProductCategory;
use App\Models\Inventory\UnitOfMeasure;
use App\Support\Tenancy\Rules\TenantExists;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
        $productId = $this->route('product')?->id;
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'sku' => [
                $required, 'string', 'max:60',
                Rule::unique('products', 'sku')->where('company_id', $companyId)->ignore($productId)->whereNull('deleted_at'),
            ],
            'barcode' => ['nullable', 'string', 'max:64'],
            'name' => [$required, 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['sometimes', Rule::enum(ProductType::class)],
            'category_id' => ['nullable', 'integer', new TenantExists(ProductCategory::class)],
            'uom_id' => ['nullable', 'integer', new TenantExists(UnitOfMeasure::class)],
            'cost_price' => ['sometimes', 'numeric', 'min:0', 'max:99999999999999'],
            'sale_price' => ['sometimes', 'numeric', 'min:0', 'max:99999999999999'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'tax_rate' => ['nullable', 'numeric', 'between:0,100'],
            'reorder_level' => ['sometimes', 'numeric', 'min:0', 'max:99999999999999'],
            'reorder_quantity' => ['sometimes', 'numeric', 'min:0', 'max:99999999999999'],
            'track_batches' => ['sometimes', 'boolean'],
            'track_serials' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'attributes' => ['nullable', 'array', 'max:50'],
            'attributes.*' => ['nullable', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_scalar($value)) {
                    $fail('Product attributes must be flat key/value pairs.');
                }
            }],
        ];
    }
}
