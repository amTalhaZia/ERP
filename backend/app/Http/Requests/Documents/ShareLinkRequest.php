<?php

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;

class ShareLinkRequest extends FormRequest
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
            'minutes' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'version' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
