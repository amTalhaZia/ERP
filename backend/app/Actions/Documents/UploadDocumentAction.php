<?php

namespace App\Actions\Documents;

use App\Enums\Documents\DocumentCategory;
use App\Enums\Documents\DocumentVisibility;
use App\Models\Documents\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Creates a document with its first version in one transaction. The creator
 * becomes the owner; the title defaults to the file name.
 */
class UploadDocumentAction
{
    public function __construct(private readonly AddDocumentVersionAction $versions) {}

    /**
     * @param  array<string, mixed>  $data  validated metadata (+ file, note)
     */
    public function handle(array $data, UploadedFile $file, User $actor): Document
    {
        return DB::transaction(function () use ($data, $file, $actor): Document {
            $metadata = Arr::except($data, ['file', 'note']);
            $metadata['title'] ??= pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'Untitled';
            $metadata['category'] ??= DocumentCategory::Other->value;
            $metadata['visibility'] ??= DocumentVisibility::Company->value;
            $metadata['owner_id'] = $actor->id;

            if (($metadata['visibility'] ?? null) !== DocumentVisibility::Restricted->value) {
                $metadata['allowed_role_ids'] = null;
            }

            $document = Document::query()->create($metadata);

            $this->versions->handle($document, $file, $actor, $data['note'] ?? null, audit: false);

            return $document->refresh();
        });
    }
}
