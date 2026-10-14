<?php

namespace App\Actions\Company;

use App\Actions\Access\ResolveTemplatePermissions;
use App\Events\CompanyProvisioned;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a company with its head-office branch, the standard role set from
 * config/erp.php, and (optionally) an owner membership. Runs outside any
 * tenant scope because the company does not exist yet.
 */
class CreateCompanyAction
{
    public function __construct(
        private readonly ResolveTemplatePermissions $templatePermissions,
        private readonly AuditLogger $audit,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes, ?User $owner = null): Company
    {
        return DB::transaction(function () use ($attributes, $owner): Company {
            $previous = $this->tenant->company();
            $previousMembership = $this->tenant->membership();
            $this->tenant->clear();

            try {
                $attributes['slug'] ??= $this->uniqueSlug($attributes['name']);
                $attributes['code'] ??= Str::upper(Str::substr(Str::slug($attributes['name'], ''), 0, 6)).'-'.Str::upper(Str::random(3));

                $company = Company::query()->create($attributes);

                Branch::withoutTenancy()->create([
                    'company_id' => $company->id,
                    'name' => 'Head Office',
                    'code' => 'HO',
                    'is_head_office' => true,
                    'address_line1' => $company->address_line1,
                    'city' => $company->city,
                    'state' => $company->state,
                    'postal_code' => $company->postal_code,
                    'country' => $company->country,
                    'timezone' => $company->timezone,
                ]);

                $roles = $this->createRoles($company);

                if ($owner !== null) {
                    CompanyUser::query()->create([
                        'company_id' => $company->id,
                        'user_id' => $owner->id,
                        'is_default' => ! $owner->memberships()->exists(),
                        'is_active' => true,
                        'joined_at' => now(),
                    ]);

                    $owner->roles()->attach($roles['company.owner']->id, ['company_id' => $company->id]);
                }

                $this->audit->log('companies.provisioned', $company, companyId: $company->id, context: [
                    'owner_id' => $owner?->id,
                    'roles' => count($roles),
                ]);

                // Let modules seed their per-company defaults (COA, periods, leave types, UoM…).
                CompanyProvisioned::dispatch($company);

                return $company;
            } finally {
                if ($previous !== null) {
                    $this->tenant->set($previous, $previousMembership);
                }
            }
        });
    }

    /**
     * @return array<string, Role>
     */
    public function createRoles(Company $company): array
    {
        $permissionIds = Permission::query()->pluck('id', 'slug');
        $roles = [];

        foreach (config('erp.role_templates') as $slug => $template) {
            $role = Role::query()->create([
                'company_id' => $company->id,
                'slug' => $slug,
                'name' => $template['name'],
                'description' => $template['description'] ?? null,
                'is_system' => true,
            ]);

            $slugs = $this->templatePermissions->handle($template['permissions']);
            $role->permissions()->sync($permissionIds->only($slugs->all())->values()->all());

            $roles[$slug] = $role;
        }

        return $roles;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'company';
        $slug = $base;
        $i = 2;

        while (Company::query()->withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
