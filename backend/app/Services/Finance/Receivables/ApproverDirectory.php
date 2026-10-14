<?php

namespace App\Services\Finance\Receivables;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Active members of a company holding a given permission — the recipients of
 * "awaiting your approval" notifications.
 */
class ApproverDirectory
{
    /**
     * @return Collection<int, User>
     */
    public function withPermission(int $companyId, string $permission, ?int $exceptUserId = null): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->when($exceptUserId !== null, fn ($q) => $q->whereKeyNot($exceptUserId))
            ->whereHas('memberships', fn ($membership) => $membership->where('company_id', $companyId)->where('is_active', true))
            ->whereHas('roles', fn ($roles) => $roles
                ->where('roles.company_id', $companyId)
                ->where('role_user.company_id', $companyId)
                ->whereHas('permissions', fn ($permissions) => $permissions->where('slug', $permission)))
            ->get();
    }
}
