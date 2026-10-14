<?php

namespace App\Http\Requests\Sales;

use App\Enums\Sales\CustomerType;
use App\Http\Requests\Sales\Rules\CompanyMember;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
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
        $companyId = app(TenantContext::class)->id();
        $customerId = $this->route('customer')?->id;
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'code' => [
                'sometimes', 'nullable', 'string', 'max:20', 'alpha_dash',
                Rule::unique('customers', 'code')->where('company_id', $companyId)->ignore($customerId)->whereNull('deleted_at'),
            ],
            'name' => [$required, 'string', 'max:150'],
            'type' => ['sometimes', Rule::enum(CustomerType::class)],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'string', 'max:200'],
            'tax_number' => ['nullable', 'string', 'max:60'],
            'currency' => ['sometimes', 'string', 'size:3', 'alpha'],
            'payment_terms_days' => ['sometimes', 'integer', 'between:0,365'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'billing_address_line1' => ['nullable', 'string', 'max:200'],
            'billing_address_line2' => ['nullable', 'string', 'max:200'],
            'billing_city' => ['nullable', 'string', 'max:100'],
            'billing_state' => ['nullable', 'string', 'max:100'],
            'billing_postal_code' => ['nullable', 'string', 'max:20'],
            'billing_country' => ['nullable', 'string', 'size:2'],
            'shipping_address_line1' => ['nullable', 'string', 'max:200'],
            'shipping_address_line2' => ['nullable', 'string', 'max:200'],
            'shipping_city' => ['nullable', 'string', 'max:100'],
            'shipping_state' => ['nullable', 'string', 'max:100'],
            'shipping_postal_code' => ['nullable', 'string', 'max:20'],
            'shipping_country' => ['nullable', 'string', 'size:2'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'sales_rep_id' => ['nullable', 'integer', new CompanyMember],
            'source' => ['nullable', 'string', 'max:40'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
