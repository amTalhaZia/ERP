<?php

namespace App\Policies\Concerns;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared policy helpers: permission check + "record belongs to the active
 * tenant" guard. Every tenant policy should combine both for record-level
 * abilities so an id from another company is never acted on even if a scope
 * were bypassed.
 */
trait ChecksPermissions
{
    protected function can(User $user, string $permission): bool
    {
        return $user->hasPermission($permission);
    }

    protected function sameTenant(Model $model): bool
    {
        $tenant = app(TenantContext::class);

        return $tenant->has() && (int) $model->getAttribute('company_id') === $tenant->id();
    }

    protected function canOn(User $user, string $permission, Model $model): bool
    {
        return $this->sameTenant($model) && $this->can($user, $permission);
    }

    protected function branchAccessible(?int $branchId): bool
    {
        return app(TenantContext::class)->canAccessBranch($branchId);
    }
}
