<?php

namespace App\Http\Requests\Sales;

use App\Http\Requests\Sales\Concerns\ValidatesDocumentLines;
use App\Http\Requests\Sales\Rules\CompanyMember;
use App\Models\Inventory\Warehouse;
use App\Models\Sales\Customer;
use App\Support\Tenancy\Rules\TenantExists;
use App\Support\Tenancy\Rules\WithinAccessibleBranch;
use Illuminate\Foundation\Http\FormRequest;

class StoreSalesOrderRequest extends FormRequest
{
    use ValidatesDocumentLines;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        $required = $creating ? 'required' : 'sometimes';

        return [
            'customer_id' => [$required, 'integer', new TenantExists(Customer::class)],
            'order_date' => ['sometimes', 'date'],
            'expected_delivery_date' => ['nullable', 'date'],
            'currency' => ['sometimes', 'string', 'size:3', 'alpha'],
            'shipping_name' => ['nullable', 'string', 'max:150'],
            'shipping_address_line1' => ['nullable', 'string', 'max:200'],
            'shipping_address_line2' => ['nullable', 'string', 'max:200'],
            'shipping_city' => ['nullable', 'string', 'max:100'],
            'shipping_state' => ['nullable', 'string', 'max:100'],
            'shipping_postal_code' => ['nullable', 'string', 'max:20'],
            'shipping_country' => ['nullable', 'string', 'size:2'],
            'warehouse_id' => ['nullable', 'integer', new TenantExists(Warehouse::class), new WithinAccessibleBranch(Warehouse::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'sales_rep_id' => ['nullable', 'integer', new CompanyMember],
        ] + $this->lineRules($creating);
    }
}
