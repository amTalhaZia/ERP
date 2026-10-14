<?php

namespace App\Support\Tenancy;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Every tenant-owned model uses this trait. It:
 *  - applies CompanyScope so queries only see the current tenant's rows,
 *  - fills company_id on create from the trusted TenantContext,
 *  - refuses to create a row for another tenant when one is active.
 *
 * Use `Model::withoutTenancy()` (platform/console only) to bypass the scope.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (Model $model): void {
            $tenant = app(TenantContext::class);

            if ($tenant->has()) {
                if ($model->company_id !== null && (int) $model->company_id !== $tenant->id()) {
                    throw new TenantMismatchException(static::class, (int) $model->company_id, $tenant->id());
                }

                $model->company_id = $tenant->id();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Query without the tenant scope. Only for platform-level and console code.
     *
     * @return Builder<static>
     */
    public static function withoutTenancy(): Builder
    {
        return static::withoutGlobalScope(CompanyScope::class);
    }

    /**
     * Explicitly constrain to a given company (platform code).
     *
     * @return Builder<static>
     */
    public static function forCompany(Company|int $company): Builder
    {
        $id = $company instanceof Company ? $company->id : $company;

        return static::withoutTenancy()->where((new static)->getTable().'.company_id', $id);
    }
}
