<?php

namespace App\Http\Requests\Inventory;

use App\Models\Inventory\ProductCategory;
use App\Support\Tenancy\Rules\TenantExists;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductCategoryRequest extends FormRequest
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
        $categoryId = $this->route('product_category')?->id;
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:120'],
            'code' => [
                $required, 'string', 'max:30', 'alpha_dash',
                Rule::unique('product_categories', 'code')->where('company_id', $companyId)->ignore($categoryId),
            ],
            'parent_id' => [
                'nullable', 'integer', new TenantExists(ProductCategory::class),
                $categoryId ? Rule::notIn([$categoryId]) : 'nullable',
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['parent_id.not_in' => 'A category cannot be its own parent.'];
    }
}
