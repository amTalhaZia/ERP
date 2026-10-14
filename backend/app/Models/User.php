<?php

namespace App\Models;

use App\Support\Audit\Auditable;
use App\Support\Tenancy\TenantContext;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'email', 'phone', 'avatar_path', 'password', 'is_active', 'platform_role', 'preferences'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, Notifiable;

    /** @var array<int, string> */
    protected array $auditExclude = [
        'two_factor_secret', 'two_factor_recovery_codes', 'preferences', 'last_login_at', 'last_login_ip',
    ];

    /** @var array<int, array<int, string>>  per-request permission cache keyed by company id */
    private array $permissionCache = [];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'preferences' => 'array',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    // -- Relationships -----------------------------------------------------

    public function memberships(): HasMany
    {
        return $this->hasMany(CompanyUser::class);
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_user')
            ->withPivot(['is_default', 'is_active', 'branch_ids', 'joined_at'])
            ->withTimestamps();
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')->withPivot('company_id')->withTimestamps();
    }

    // -- Platform ------------------------------------------------------------

    public function isPlatformAdmin(): bool
    {
        return $this->is_active && in_array($this->platform_role, [Role::PLATFORM_OWNER, Role::PLATFORM_ADMIN], true);
    }

    public function isPlatformOwner(): bool
    {
        return $this->is_active && $this->platform_role === Role::PLATFORM_OWNER;
    }

    // -- Permissions -------------------------------------------------------

    /**
     * Permission slugs held in the given company (defaults to the current tenant).
     *
     * @return array<int, string>
     */
    public function permissionsFor(?int $companyId = null): array
    {
        $companyId ??= app(TenantContext::class)->id();

        if ($companyId === null) {
            return [];
        }

        return $this->permissionCache[$companyId] ??= Permission::query()
            ->whereHas('roles', function ($roles) use ($companyId) {
                $roles->where('roles.company_id', $companyId)
                    ->whereHas('users', fn ($users) => $users
                        ->where('users.id', $this->id)
                        ->where('role_user.company_id', $companyId));
            })
            ->pluck('slug')
            ->all();
    }

    public function hasPermission(string $slug, ?int $companyId = null): bool
    {
        if (str_starts_with($slug, 'platform.')) {
            return $this->isPlatformAdmin();
        }

        return in_array($slug, $this->permissionsFor($companyId), true);
    }

    /**
     * @param  array<int, string>  $slugs
     */
    public function hasAnyPermission(array $slugs, ?int $companyId = null): bool
    {
        foreach ($slugs as $slug) {
            if ($this->hasPermission($slug, $companyId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $slugs
     */
    public function hasAllPermissions(array $slugs, ?int $companyId = null): bool
    {
        foreach ($slugs as $slug) {
            if (! $this->hasPermission($slug, $companyId)) {
                return false;
            }
        }

        return true;
    }

    public function forgetPermissionCache(): void
    {
        $this->permissionCache = [];
    }

    // -- Misc ----------------------------------------------------------------

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /**
     * @return array{theme: string, high_contrast: bool, locale: string}
     */
    public function uiPreferences(): array
    {
        return array_merge(
            ['theme' => 'system', 'high_contrast' => false, 'locale' => 'en'],
            $this->preferences ?? [],
        );
    }
}
