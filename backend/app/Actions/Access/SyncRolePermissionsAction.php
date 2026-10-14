<?php

namespace App\Actions\Access;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Replaces a role's permission set (non-platform permissions only) and writes
 * an explicit `roles.permissions_changed` audit entry listing added/removed
 * slugs. Permission changes are kept forever by `erp:prune`.
 */
class SyncRolePermissionsAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<int, int>  $permissionIds
     * @return array{added: array<int, string>, removed: array<int, string>}
     */
    public function handle(Role $role, array $permissionIds): array
    {
        return DB::transaction(function () use ($role, $permissionIds): array {
            $before = $role->permissions()->pluck('slug')->sort()->values();

            $ids = Permission::query()
                ->where('is_platform', false)
                ->whereIn('id', array_map('intval', $permissionIds))
                ->pluck('id')
                ->all();

            $role->permissions()->sync($ids);

            $after = $role->permissions()->pluck('slug')->sort()->values();

            $added = $after->diff($before)->values()->all();
            $removed = $before->diff($after)->values()->all();

            if ($added !== [] || $removed !== []) {
                $this->audit->log('roles.permissions_changed', $role, ['permissions' => $before->all()], ['permissions' => $after->all()], [
                    'added' => $added,
                    'removed' => $removed,
                ], companyId: $role->company_id);
            }

            return ['added' => $added, 'removed' => $removed];
        });
    }
}
