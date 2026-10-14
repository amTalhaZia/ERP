<?php

namespace App\Http\Requests\Sales;

use App\Http\Requests\Sales\Concerns\ValidatesDocumentLines;
use App\Http\Requests\Sales\Rules\CompanyMember;
use App\Models\Sales\Customer;
use App\Support\Tenancy\Rules\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

class StoreQuotationRequest extends FormRequest
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
            'date' => ['sometimes', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:date'],
            'currency' => ['sometimes', 'string', 'size:3', 'alpha'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'sales_rep_id' => ['nullable', 'integer', new CompanyMember],
        ] + $this->lineRules($creating);
    }
}
