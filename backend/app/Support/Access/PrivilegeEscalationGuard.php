<?php

namespace App\Support\Access;

use App\Exceptions\Access\PrivilegeEscalationException;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Server-side rules that stop company members from escalating privileges.
 * Members holding the Company Owner role (`company.owner`) are exempt;
 * everyone else:
 *
 *  1. may not assign or remove the Company Owner role;
 *  2. may only assign roles whose permissions are a subset of their own;
 *  3. may not change the permissions of a role they currently hold
 *     (e.g. an administrator widening "Company Administrator");
 *  4. may not grant a permission they do not hold themselves when creating
 *     or editing a role.
 *
 * The Company Owner role itself is locked for everyone (RoleController).
 */
class PrivilegeEscalationGuard
{
    public const OWNER_SLUG = 'company.owner';

    public function isOwner(User $actor, int $companyId): bool
    {
        return $this->heldRoles($actor, $companyId)->contains('slug', self::OWNER_SLUG);
    }

    /**
     * Rules 1 + 2 for invitations and membership updates.
     *
     * @param  iterable<int|string>  $newRoleIds  role ids the member will hold
     * @param  iterable<int|string>  $currentRoleIds  role ids the member holds today (empty on invite)
     */
    public function assertCanAssignRoles(User $actor, int $companyId, iterable $newRoleIds, iterable $currentRoleIds = []): void
    {
        if ($this->isOwner($actor, $companyId)) {
            return;
        }

        $new = $this->ids($newRoleIds);
        $current = $this->ids($currentRoleIds);
        $added = $new->diff($current);
        $removed = $current->diff($new);

        $ownerRoleId = Role::forTenant($companyId)->where('slug', self::OWNER_SLUG)->value('id');

        if ($ownerRoleId !== null && $added->merge($removed)->contains((int) $ownerRoleId)) {
            throw new PrivilegeEscalationException('Only a Company Owner can assign or remove the Company Owner role.');
        }

        if ($added->isEmpty()) {
            return;
        }

        $granted = Permission::query()
            ->whereHas('roles', fn ($q) => $q->where('roles.company_id', $companyId)->whereIn('roles.id', $added->all()))
            ->pluck('slug');

        $this->assertHolds($actor, $companyId, $granted, 'You cannot assign a role that grants permissions you do not hold yourself');
    }

    /**
     * Rules 3 + 4 for creating a role (`$role` null) or replacing a role's permissions.
     *
     * @param  array<int, int|string>  $permissionIds
     */
    public function assertCanSetRolePermissions(User $actor, int $companyId, ?Role $role, array $permissionIds): void
    {
        if ($this->isOwner($actor, $companyId)) {
            return;
        }

        if ($role !== null && $this->heldRoles($actor, $companyId)->contains('id', $role->id)) {
            throw new PrivilegeEscalationException('Only a Company Owner can change the permissions of a role you hold yourself.');
        }

        $requested = Permission::query()
            ->where('is_platform', false)
            ->whereIn('id', $this->ids($permissionIds)->all())
            ->pluck('slug');

        $existing = $role === null ? collect() : $role->permissions()->pluck('slug');

        $this->assertHolds($actor, $companyId, $requested->diff($existing), 'You cannot grant permissions you do not hold yourself');
    }

    /**
     * @param  Collection<int, string>  $slugs
     */
    private function assertHolds(User $actor, int $companyId, Collection $slugs, string $message): void
    {
        $missing = $slugs->diff($actor->permissionsFor($companyId))->unique()->sort()->values();

        if ($missing->isNotEmpty()) {
            throw new PrivilegeEscalationException($message.': '.$missing->implode(', ').'.');
        }
    }

    /**
     * @return Collection<int, object{id: int, slug: string}>
     */
    private function heldRoles(User $actor, int $companyId): Collection
    {
        return DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $actor->id)
            ->where('role_user.company_id', $companyId)
            ->where('roles.company_id', $companyId)
            ->get(['roles.id', 'roles.slug'])
            ->map(fn (object $row) => (object) ['id' => (int) $row->id, 'slug' => (string) $row->slug]);
    }

    /**
     * @param  iterable<int|string>  $ids
     * @return Collection<int, int>
     */
    private function ids(iterable $ids): Collection
    {
        return collect($ids)->map(fn ($id) => (int) $id)->unique()->values();
    }
}
