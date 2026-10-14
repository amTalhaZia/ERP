<?php

namespace App\Http\Resources\Auth;

use App\Models\Branch;
use App\Models\CompanyUser;
use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The payload the SPA needs after login / on refresh: the user, their company
 * memberships, the active company, and the permission slugs for it.
 *
 * @mixin User
 */
class SessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $tenant = app(TenantContext::class);

        $memberships = CompanyUser::query()
            ->with('company:id,name,slug,logo_path,currency,timezone,is_active')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereHas('company', fn ($q) => $q->where('is_active', true))
            ->get();

        $roleNames = Role::query()
            ->join('role_user', 'role_user.role_id', '=', 'roles.id')
            ->where('role_user.user_id', $user->id)
            ->whereNotNull('role_user.company_id')
            ->get(['roles.name', 'role_user.company_id'])
            ->groupBy('company_id')
            ->map(fn ($rows) => $rows->pluck('name')->implode(', '));

        $companies = $memberships->map(fn (CompanyUser $m) => [
            'id' => $m->company->id,
            'name' => $m->company->name,
            'slug' => $m->company->slug,
            'logo_url' => $m->company->logoUrl(),
            'currency' => $m->company->currency,
            'timezone' => $m->company->timezone,
            'role' => $roleNames->get($m->company_id, ''),
            'branch_ids' => $m->branch_ids,
            'is_default' => $m->is_default,
        ])->values();

        $current = $tenant->has() ? $companies->firstWhere('id', $tenant->id()) : null;

        $branches = $tenant->has()
            ? Branch::query()
                ->where('is_active', true)
                ->when($tenant->allowedBranchIds() !== null, fn ($q) => $q->whereIn('id', $tenant->allowedBranchIds()))
                ->orderByDesc('is_head_office')->orderBy('name')
                ->get(['id', 'company_id', 'name', 'code', 'is_head_office'])
            : collect();

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatarUrl(),
                'is_platform_admin' => $user->isPlatformAdmin(),
                'platform_role' => $user->platform_role,
                'two_factor_enabled' => $user->hasTwoFactorEnabled(),
                'email_verified_at' => $user->email_verified_at?->toJSON(),
                'last_login_at' => $user->last_login_at?->toJSON(),
                'preferences' => $user->uiPreferences(),
            ],
            'companies' => $companies,
            'current_company' => $current,
            'permissions' => $tenant->has() ? $user->permissionsFor($tenant->id()) : [],
            'branches' => $branches,
        ];
    }
}
