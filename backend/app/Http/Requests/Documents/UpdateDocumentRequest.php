<?php

namespace App\Http\Requests\Documents;

use App\Http\Requests\Documents\Concerns\DocumentMetadataRules;
use Illuminate\Foundation\Http\FormRequest;

/** Metadata-only update; new file content goes through POST /documents/{id}/versions. */
class UpdateDocumentRequest extends FormRequest
{
    use DocumentMetadataRules;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeTags();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->metadataRules(), [
            'is_archived' => ['sometimes', 'boolean'],
        ]);
    }
}
