<?php

namespace App\Actions\Documents;

use App\Models\Documents\Document;
use App\Models\Documents\DocumentVersion;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores an uploaded file as the next version of a document on the PRIVATE
 * `local` disk at documents/{company_id}/{uuid}.{ext}, records its SHA-256 and
 * makes it current. The stored file is removed again if the DB write fails.
 */
class AddDocumentVersionAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Document $document, UploadedFile $file, User $actor, ?string $note = null, bool $audit = true): DocumentVersion
    {
        $path = $this->store($document->company_id, $file);

        try {
            $version = DB::transaction(function () use ($document, $file, $actor, $note, $path): DocumentVersion {
                // Serialise concurrent uploads to the same document.
                Document::query()->whereKey($document->id)->lockForUpdate()->first(['id']);

                $next = ((int) DocumentVersion::query()->where('document_id', $document->id)->max('version')) + 1;

                $version = DocumentVersion::query()->create([
                    'document_id' => $document->id,
                    'version' => $next,
                    'file_path' => $path,
                    'original_name' => $this->safeOriginalName($file),
                    'mime' => $file->getMimeType() ?? $file->getClientMimeType(),
                    'size_bytes' => (int) $file->getSize(),
                    'checksum_sha256' => (string) hash_file('sha256', $file->getRealPath()),
                    'uploaded_by' => $actor->id,
                    'note' => $note,
                ]);

                $document->forceFill(['current_version_id' => $version->id])->saveQuietly();

                return $version;
            });
        } catch (\Throwable $e) {
            Storage::disk(DocumentVersion::DISK)->delete($path);

            throw $e;
        }

        if ($audit) {
            $this->audit->log('documents.version_added', $document, null, [
                'version' => $version->version,
                'original_name' => $version->original_name,
                'size_bytes' => $version->size_bytes,
                'checksum_sha256' => $version->checksum_sha256,
            ]);
        }

        return $version;
    }

    private function store(int $companyId, UploadedFile $file): string
    {
        $extension = Str::lower($file->getClientOriginalExtension() ?: ($file->guessExtension() ?? 'bin'));

        return (string) Storage::disk(DocumentVersion::DISK)->putFileAs(
            "documents/{$companyId}",
            $file,
            Str::uuid()->toString().'.'.$extension,
        );
    }

    private function safeOriginalName(UploadedFile $file): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '', $file->getClientOriginalName()) ?? 'file';

        return Str::limit($name !== '' ? $name : 'file', 250, '');
    }
}
