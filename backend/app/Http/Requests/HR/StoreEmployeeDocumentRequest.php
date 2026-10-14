<?php

namespace App\Http\Requests\HR;

use App\Enums\HR\DocumentCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeDocumentRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::enum(DocumentCategory::class)],
            'file' => ['required', 'file', 'mimes:'.implode(',', config('erp.uploads.allowed_mimes')), 'max:'.config('erp.uploads.max_kb')],
            'expires_at' => ['nullable', 'date'],
            'is_confidential' => ['sometimes', 'boolean'],
        ];
    }
}
