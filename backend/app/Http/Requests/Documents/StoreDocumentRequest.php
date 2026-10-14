<?php

namespace App\Http\Requests\Documents;

use App\Http\Requests\Documents\Concerns\DocumentMetadataRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Multipart upload: `file` + metadata. Allowed types and size come from
 * config('erp.uploads'); both the sniffed content type (`mimes`) and the
 * client extension (`extensions`) must be on the allow-list.
 */
class StoreDocumentRequest extends FormRequest
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
            'file' => self::fileRules(),
            'note' => ['nullable', 'string', 'max:500'],
        ]);
    }

    /**
     * @return array<int, string>
     */
    public static function fileRules(): array
    {
        $allowed = implode(',', (array) config('erp.uploads.allowed_mimes'));

        return [
            'required', 'file',
            'mimes:'.$allowed,
            'extensions:'.$allowed,
            'max:'.(int) config('erp.uploads.max_kb'),
        ];
    }
}
