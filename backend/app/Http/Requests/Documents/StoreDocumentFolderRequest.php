<?php

namespace App\Http\Requests\Documents;

use App\Models\Documents\DocumentFolder;
use App\Support\Tenancy\Rules\TenantExists;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentFolderRequest extends FormRequest
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
        /** @var DocumentFolder|null $current */
        $current = $this->route('folder');

        return [
            'name' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:150'],
            'parent_id' => [
                'nullable', 'integer', new TenantExists(DocumentFolder::class),
                function (string $attribute, mixed $value, Closure $fail) use ($current): void {
                    $parent = DocumentFolder::query()->find($value);

                    if ($parent === null) {
                        return;
                    }

                    if (! $parent->isVisibleTo($this->user())) {
                        $fail('The selected :attribute is invalid.');

                        return;
                    }

                    // Walk up from the new parent: it must not be this folder or one of its descendants.
                    $cursor = $parent;
                    $guard = 0;

                    while ($current !== null && $cursor !== null && $guard++ < 50) {
                        if ($cursor->id === $current->id) {
                            $fail('A folder cannot be moved inside itself.');

                            return;
                        }

                        $cursor = $cursor->parent_id ? DocumentFolder::query()->find($cursor->parent_id) : null;
                    }
                },
            ],
            'is_private' => ['sometimes', 'boolean'],
        ];
    }
}
