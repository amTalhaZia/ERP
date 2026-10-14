<?php

namespace App\Http\Requests\Procurement;

use App\Enums\Procurement\RequisitionPriority;
use App\Models\Department;
use App\Models\Inventory\Product;
use App\Support\Tenancy\Rules\AccessibleBranch;
use App\Support\Tenancy\Rules\TenantExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Lines are the full desired set on both create and update (wholesale replace).
 */
class StorePurchaseRequisitionRequest extends FormRequest
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
            'department_id' => ['nullable', 'integer', new TenantExists(Department::class)],
            'branch_id' => ['nullable', 'integer', new AccessibleBranch],
            'needed_by' => ['nullable', 'date'],
            'priority' => ['sometimes', Rule::enum(RequisitionPriority::class)],
            'justification' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.product_id' => ['nullable', 'integer', new TenantExists(Product::class)],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.uom' => ['nullable', 'string', 'max:20'],
            'lines.*.estimated_unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
