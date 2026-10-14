<?php

namespace App\Models\Documents;

use App\Models\User;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Documents\DocumentFolderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A folder groups documents. A private folder (and everything filed in it)
 * is visible to its owner only.
 */
#[Fillable(['company_id', 'parent_id', 'name', 'is_private', 'owner_id'])]
class DocumentFolder extends Model
{
    /** @use HasFactory<DocumentFolderFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(DocumentFolder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(DocumentFolder::class, 'parent_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'folder_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function isVisibleTo(User $user): bool
    {
        return ! $this->is_private || $this->owner_id === $user->id;
    }

    /**
     * @param  Builder<DocumentFolder>  $query
     * @return Builder<DocumentFolder>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('document_folders.is_private', false)
            ->orWhere('document_folders.owner_id', $user->id));
    }
}
