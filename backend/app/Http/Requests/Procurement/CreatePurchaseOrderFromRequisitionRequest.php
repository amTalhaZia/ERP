<?php

namespace App\Http\Requests\Procurement;

use App\Models\Inventory\Warehouse;
use App\Models\Procurement\Supplier;
use App\Support\Tenancy\Rules\TenantExists;
use App\Support\Tenancy\Rules\WithinAccessibleBranch;
use Illuminate\Foundation\Http\FormRequest;

class CreatePurchaseOrderFromRequisitionRequest extends FormRequest
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
            'supplier_id' => ['required', 'integer', new TenantExists(Supplier::class)],
            'expected_date' => ['nullable', 'date'],
            'warehouse_id' => ['nullable', 'integer', new TenantExists(Warehouse::class), new WithinAccessibleBranch(Warehouse::class)],
            'delivery_address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
