<?php

namespace App\Http\Requests\Projects;

use App\Models\Documents\Document;
use App\Support\Tenancy\Rules\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('currency'))) {
            $this->merge(['currency' => strtoupper($this->input('currency'))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'date' => [$required, 'date'],
            'category' => [$required, 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:500'],
            'amount' => [$required, 'numeric', 'gt:0', 'max:99999999999999', 'decimal:0,4'],
            'currency' => ['sometimes', 'string', 'size:3', 'alpha'],
            'receipt_document_id' => ['nullable', 'integer', new TenantExists(Document::class)],
        ];
    }
}
