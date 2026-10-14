<?php

namespace App\Http\Requests\Inventory;

use App\Models\Inventory\Batch;
use App\Models\Inventory\Product;
use App\Models\Inventory\StorageLocation;
use App\Models\Inventory\Warehouse;
use App\Support\Tenancy\Rules\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ad-hoc stock issue (consumption, samples, internal use). Costed at the
 * current average, so no unit cost is accepted.
 */
class IssueStockRequest extends FormRequest
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
            'lines.*.batch_id' => ['nullable', 'integer', new TenantExists(Batch::class)],
            'lines.*.serials' => ['nullable', 'array', 'max:1000'],
            'lines.*.serials.*' => ['required', 'string', 'max:80', 'distinct'],
        ];
    }
}
