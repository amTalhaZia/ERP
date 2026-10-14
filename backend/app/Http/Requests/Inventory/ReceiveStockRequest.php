<?php

namespace App\Http\Requests\Inventory;

use App\Models\Inventory\Product;
use App\Models\Inventory\StorageLocation;
use App\Models\Inventory\Warehouse;
use App\Support\Tenancy\Rules\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ad-hoc goods receipt (no source document): one or more product lines into a
 * warehouse / optional location, each at an explicit unit cost.
 */
class ReceiveStockRequest extends FormRequest
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
            'warehouse_id' => ['required', 'integer', new TenantExists(Warehouse::class, where: ['is_active' => true])],
            'location_id' => [
                'nullable', 'integer',
                new TenantExists(StorageLocation::class, where: ['warehouse_id' => (int) $this->input('warehouse_id')]),
            ],
            'reason' => ['nullable', 'string', 'max:200'],
            'moved_at' => ['nullable', 'date'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.product_id' => ['required', 'integer', new TenantExists(Product::class, where: ['type' => 'goods', 'is_active' => true])],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999999999999'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:99999999999999'],
            'lines.*.batch_number' => ['nullable', 'string', 'max:60'],
            'lines.*.manufactured_at' => ['nullable', 'date'],
            'lines.*.expires_at' => ['nullable', 'date'],
            'lines.*.serials' => ['nullable', 'array', 'max:1000'],
            'lines.*.serials.*' => ['required', 'string', 'max:80', 'distinct'],
        ];
    }
}
