<?php

namespace App\Http\Requests\Documents\Concerns;

use App\Enums\Documents\DocumentableType;
use App\Enums\Documents\DocumentCategory;
use App\Enums\Documents\DocumentVisibility;
use App\Models\Documents\DocumentFolder;
use App\Support\Tenancy\Rules\TenantExists;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * Metadata rules shared by upload (POST /documents) and update (PATCH).
 */
trait DocumentMetadataRules
{
    protected function normalizeTags(): void
    {
        if (is_array($this->input('tags'))) {
            $this->merge(['tags' => array_values(array_map(
                fn ($tag) => is_string($tag) ? mb_strtolower(trim($tag)) : $tag,
                $this->input('tags'),
            ))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function metadataRules(): array
    {
        $companyId = app(TenantContext::class)->id();

        return [
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['sometimes', Rule::enum(DocumentCategory::class)],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:40', 'distinct'],
            'folder_id' => [
                'nullable', 'integer', new TenantExists(DocumentFolder::class),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $folder = DocumentFolder::query()->find($value);

                    if ($folder !== null && ! $folder->isVisibleTo($this->user())) {
                        $fail('The selected :attribute is invalid.');
                    }
                },
            ],
            'documentable_type' => ['nullable', 'required_with:documentable_id', Rule::enum(DocumentableType::class)],
            'documentable_id' => ['nullable', 'required_with:documentable_type', 'integer', $this->documentableRule()],
            'visibility' => ['sometimes', Rule::enum(DocumentVisibility::class)],
            'allowed_role_ids' => ['nullable', 'array', 'max:50', 'required_if:visibility,'.DocumentVisibility::Restricted->value],
            'allowed_role_ids.*' => ['integer', 'distinct', Rule::exists('roles', 'id')->where('company_id', $companyId)],
            'retention_until' => ['nullable', 'date'],
        ];
    }

    /**
     * The linked record must exist in the current company. Tables owned by
     * modules not installed in this deployment are skipped (no FK by design).
     */
    private function documentableRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $type = DocumentableType::tryFrom((string) $this->input('documentable_type'));

            if ($type === null) {
                return;
            }

            $table = $type->table();

            if (! Schema::hasTable($table)) {
                return;
            }

            $exists = DB::table($table)
                ->where('id', $value)
                ->where('company_id', app(TenantContext::class)->id())
                ->when(Schema::hasColumn($table, 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
                ->exists();

            if (! $exists) {
                $fail('The linked record does not exist.');
            }
        };
    }
}
