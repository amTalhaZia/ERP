<?php

namespace App\Http\Requests\Procurement;

use App\Models\Inventory\Warehouse;
use App\Support\Tenancy\Rules\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Line membership in the order and the "≤ remaining" rule are enforced by
 * SaveGoodsReceiptAction (it needs the order's lines) and surface as 422s on
 * `lines.N.purchase_order_line_id` / `lines.N.quantity_received`.
 */
class StoreGoodsReceiptRequest extends FormRequest
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
            'received_date' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'date'],
            'warehouse_id' => ['nullable', 'integer', new TenantExists(Warehouse::class)],
            'supplier_delivery_note' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.purchase_order_line_id' => ['required', 'integer', 'min:1'],
            'lines.*.quantity_received' => ['required', 'numeric', 'min:0'],
            'lines.*.quantity_rejected' => ['nullable', 'numeric', 'min:0'],
            'lines.*.rejection_reason' => ['nullable', 'string', 'max:255'],
            'lines.*.batch_number' => ['nullable', 'string', 'max:60'],
            'lines.*.expires_at' => ['nullable', 'date'],
        ];
    }
}
