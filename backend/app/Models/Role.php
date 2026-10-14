<?php

namespace App\Models;

use App\Support\Audit\Auditable;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Not tenant-scoped automatically because platform roles have company_id NULL;
 * controllers use the `forTenant()` scope explicitly.
 */
#[Fillable(['company_id', 'slug', 'name', 'description', 'is_system'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use Auditable, HasFactory;

    public const PLATFORM_OWNER = 'platform.owner';

    public const PLATFORM_ADMIN = 'platform.admin';

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user')->withPivot('company_id')->withTimestamps();
    }

    /**
     * @param  Builder<Role>  $query
     * @return Builder<Role>
     */
    public function scopeForTenant(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * @param  Builder<Role>  $query
     * @return Builder<Role>
     */
    public function scopePlatform(Builder $query): Builder
    {
        return $query->whereNull('company_id');
    }

    public function isPlatformRole(): bool
    {
        return $this->company_id === null;
    }
}
