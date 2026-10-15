<?php

namespace App\Http\Requests\Inventory;

use App\Enums\Inventory\StockAdjustmentReason;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Support\Tenancy\Rules\TenantExists;
use App\Support\Tenancy\Rules\WithinAccessibleBranch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockAdjustmentRequest extends FormRequest
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
            'warehouse_id' => [$required, 'integer', new TenantExists(Warehouse::class, where: ['is_active' => true]), new WithinAccessibleBranch(Warehouse::class)],
            'reason' => [$required, Rule::enum(StockAdjustmentReason::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => [$required, 'array', 'min:1', 'max:500'],
            'lines.*.product_id' => ['required', 'integer', 'distinct', new TenantExists(Product::class, where: ['type' => 'goods', 'is_active' => true])],
            'lines.*.counted_quantity' => ['required', 'numeric', 'min:0', 'max:99999999999999'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999999999'],
        ];
    }
}
