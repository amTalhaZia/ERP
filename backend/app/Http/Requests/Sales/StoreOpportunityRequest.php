<?php

namespace App\Http\Requests\Sales;

use App\Http\Requests\Sales\Rules\CompanyMember;
use App\Models\Sales\Customer;
use App\Models\Sales\Lead;
use App\Models\Sales\PipelineStage;
use App\Support\Tenancy\Rules\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

class StoreOpportunityRequest extends FormRequest
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
            'title' => [$required, 'string', 'max:200'],
            'customer_id' => ['nullable', 'integer', new TenantExists(Customer::class)],
            'lead_id' => ['nullable', 'integer', new TenantExists(Lead::class)],
            'stage_id' => [$required, 'integer', new TenantExists(PipelineStage::class)],
            'value' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3', 'alpha'],
            'probability' => ['sometimes', 'nullable', 'integer', 'between:0,100'],
            'expected_close_date' => ['nullable', 'date'],
            'lost_reason' => ['nullable', 'string', 'max:500'],
            'owner_id' => ['nullable', 'integer', new CompanyMember],
        ];
    }
}
