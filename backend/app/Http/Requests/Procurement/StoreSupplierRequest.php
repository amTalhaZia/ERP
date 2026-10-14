<?php

namespace App\Http\Requests\Procurement;

use App\Enums\Procurement\SupplierType;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierRequest extends FormRequest
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
        $supplierId = $this->route('supplier')?->id;
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:150'],
            // Omitted on create → auto-numbered SUP-00001.
            'code' => [
                'sometimes', 'nullable', 'string', 'max:20', 'alpha_dash',
                Rule::unique('suppliers', 'code')->where('company_id', $companyId)->ignore($supplierId)->whereNull('deleted_at'),
            ],
            'type' => ['sometimes', Rule::enum(SupplierType::class)],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url', 'max:200'],
            'tax_number' => ['nullable', 'string', 'max:60'],
            'currency' => ['sometimes', 'string', 'size:3', 'alpha'],
            'payment_terms_days' => ['sometimes', 'integer', 'between:0,365'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'address_line1' => ['nullable', 'string', 'max:200'],
            'address_line2' => ['nullable', 'string', 'max:200'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'size:2'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'bank_account_number' => ['nullable', 'string', 'max:64'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'lead_time_days' => ['nullable', 'integer', 'between:0,365'],
            'categories' => ['nullable', 'array', 'max:20'],
            'categories.*' => ['string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('currency') && is_string($this->input('currency'))) {
            $this->merge(['currency' => strtoupper($this->input('currency'))]);
        }
    }
}
