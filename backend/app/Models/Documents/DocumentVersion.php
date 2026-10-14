<?php

namespace App\Models\Documents;

use App\Models\User;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Documents\DocumentVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An immutable uploaded file. `file_path` points into the PRIVATE `local`
 * disk and is never exposed to clients; files are streamed via the download
 * endpoint after authorization.
 */
#[Fillable([
    'company_id', 'document_id', 'version', 'file_path', 'original_name', 'mime', 'size_bytes',
    'checksum_sha256', 'uploaded_by', 'note',
])]
#[Hidden(['file_path'])]
class DocumentVersion extends Model
{
    /** @use HasFactory<DocumentVersionFactory> */
    use BelongsToCompany, HasFactory;

    public const DISK = 'local';

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'size_bytes' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
