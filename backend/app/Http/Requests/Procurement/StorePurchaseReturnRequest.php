<?php

namespace App\Http\Requests\Procurement;

use App\Enums\Procurement\PurchaseReturnReason;
use App\Models\Procurement\GoodsReceipt;
use App\Models\Procurement\PurchaseOrder;
use App\Support\Tenancy\Rules\TenantExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseReturnRequest extends FormRequest
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
            'purchase_order_id' => [$this->isMethod('POST') ? 'required' : 'prohibited', 'integer', new TenantExists(PurchaseOrder::class)],
            'goods_receipt_id' => ['nullable', 'integer', new TenantExists(GoodsReceipt::class)],
            'return_date' => [$required, 'date'],
            'reason' => [$required, Rule::enum(PurchaseReturnReason::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.purchase_order_line_id' => ['required', 'integer', 'min:1'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
