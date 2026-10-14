<?php

namespace App\Models\Documents;

use App\Enums\Documents\DocumentableType;
use App\Enums\Documents\DocumentCategory;
use App\Enums\Documents\DocumentVisibility;
use App\Models\User;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use App\Support\Tenancy\TenantContext;
use Database\Factories\Documents\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Document metadata. The file content lives in DocumentVersion rows.
 *
 * Access (on top of `documents.view`):
 *  - Company:    everyone in the company;
 *  - Restricted: owner, holders of a role in `allowed_role_ids`, or `documents.manage`;
 *  - Private:    owner only;
 *  - in a private folder: folder owner only;
 *  - linked to an employee: additionally requires `employees.documents.view`.
 */
#[Fillable([
    'company_id', 'title', 'description', 'category', 'tags', 'folder_id', 'documentable_type', 'documentable_id',
    'visibility', 'allowed_role_ids', 'owner_id', 'current_version_id', 'retention_until', 'is_archived', 'archived_at',
])]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use Auditable, BelongsToCompany, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'category' => DocumentCategory::class,
            'visibility' => DocumentVisibility::class,
            'tags' => 'array',
            'allowed_role_ids' => 'array',
            'documentable_id' => 'integer',
            'retention_until' => 'date',
            'is_archived' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(DocumentFolder::class, 'folder_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('version');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'current_version_id');
    }

    public function isEmployeeDocument(): bool
    {
        return $this->documentable_type === DocumentableType::Employee->value;
    }

    /**
     * Role ids the user holds in the current company.
     *
     * @return array<int, int>
     */
    public static function roleIdsFor(User $user): array
    {
        $companyId = app(TenantContext::class)->id();

        return DB::table('role_user')
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->pluck('role_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** Record-level visibility (the caller still checks `documents.view`). */
    public function isVisibleTo(User $user): bool
    {
        if ($this->isEmployeeDocument() && ! $user->hasPermission('employees.documents.view')) {
            return false;
        }

        if ($this->folder_id !== null) {
            $this->loadMissing('folder');

            if ($this->folder !== null && ! $this->folder->isVisibleTo($user)) {
                return false;
            }
        }

        if ($this->owner_id === $user->id) {
            return true;
        }

        return match ($this->visibility) {
            DocumentVisibility::Company => true,
            DocumentVisibility::Private => false,
            DocumentVisibility::Restricted => $user->hasPermission('documents.manage')
                || array_intersect(array_map('intval', $this->allowed_role_ids ?? []), self::roleIdsFor($user)) !== [],
        };
    }

    /**
     * Query-level equivalent of isVisibleTo() for lists and search.
     *
     * @param  Builder<Document>  $query
     * @return Builder<Document>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->hasPermission('employees.documents.view')) {
            $query->where(fn (Builder $q) => $q->whereNull('documents.documentable_type')
                ->orWhere('documents.documentable_type', '!=', DocumentableType::Employee->value));
        }

        $query->where(fn (Builder $q) => $q->whereNull('documents.folder_id')
            ->orWhereHas('folder', fn (Builder $folder) => $folder->visibleTo($user)));

        $canManage = $user->hasPermission('documents.manage');
        $roleIds = $canManage ? [] : self::roleIdsFor($user);

        return $query->where(function (Builder $q) use ($user, $canManage, $roleIds): void {
            $q->where('documents.visibility', DocumentVisibility::Company->value)
                ->orWhere('documents.owner_id', $user->id)
                ->orWhere(function (Builder $restricted) use ($canManage, $roleIds): void {
                    $restricted->where('documents.visibility', DocumentVisibility::Restricted->value);

                    if ($canManage) {
                        return;
                    }

                    if ($roleIds === []) {
                        $restricted->whereRaw('1 = 0');

                        return;
                    }

                    $restricted->where(function (Builder $roles) use ($roleIds): void {
                        foreach ($roleIds as $roleId) {
                            $roles->orWhereJsonContains('documents.allowed_role_ids', $roleId);
                        }
                    });
                });
        });
    }
}
