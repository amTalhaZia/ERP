<?php

namespace App\Models\Reports;

use App\Models\User;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A queued report export. The generated file is stored on the private
 * `local` disk and downloadable only by its owner until `expires_at`.
 */
#[Fillable([
    'company_id', 'user_id', 'report_key', 'format', 'params', 'status',
    'file_path', 'row_count', 'error', 'started_at', 'finished_at', 'expires_at',
])]
class ExportJob extends Model
{
    use BelongsToCompany, HasUuids, Prunable;

    public const DISK = 'local';

    protected function casts(): array
    {
        return [
            'params' => 'array',
            'status' => ExportJobStatus::class,
            'row_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isDownloadable(): bool
    {
        return $this->status === ExportJobStatus::Completed && $this->file_path !== null && ! $this->isExpired();
    }

    /**
     * Expired exports (any tenant) are removed by `model:prune`.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::withoutTenancy()->where('expires_at', '<', now());
    }

    protected function pruning(): void
    {
        if ($this->file_path !== null) {
            Storage::disk(self::DISK)->delete($this->file_path);
        }
    }
}
