<?php

namespace App\Http\Requests\Sales;

use App\Enums\Sales\LeadSource;
use App\Enums\Sales\LeadStatus;
use App\Http\Requests\Sales\Rules\CompanyMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
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
            'name' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:150'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'source' => ['sometimes', Rule::enum(LeadSource::class)],
            // Converted is set only by the convert action.
            'status' => ['sometimes', Rule::enum(LeadStatus::class)->except(LeadStatus::Converted)],
            'estimated_value' => ['nullable', 'numeric', 'min:0'],
            'assigned_to' => ['nullable', 'integer', new CompanyMember],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
