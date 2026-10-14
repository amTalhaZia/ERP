<?php

namespace App\Http\Requests\Inventory;

use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Support\Tenancy\Rules\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

class StoreStockTransferRequest extends FormRequest
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
            'from_warehouse_id' => [$required, 'integer', new TenantExists(Warehouse::class, where: ['is_active' => true])],
            'to_warehouse_id' => [$required, 'integer', 'different:from_warehouse_id', new TenantExists(Warehouse::class, where: ['is_active' => true])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => [$required, 'array', 'min:1', 'max:200'],
            'lines.*.product_id' => ['required', 'integer', 'distinct', new TenantExists(Product::class, where: ['type' => 'goods', 'is_active' => true])],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999999999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['to_warehouse_id.different' => 'The destination must differ from the source warehouse.'];
    }
}
