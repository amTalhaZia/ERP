<?php

namespace App\Actions\Access;

use App\Models\CompanyUser;
use App\Models\Role;
use App\Models\User;
use App\Support\Access\PrivilegeEscalationGuard;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Updates a company membership: roles (scoped to the company), branch
 * restriction and membership-level activation. Guards the acting user against
 * locking themselves out.
 */
class UpdateMembershipAction
{
    public function __construct(private readonly AuditLogger $audit, private readonly PrivilegeEscalationGuard $guard) {}

    /**
     * @param  array{role_ids?: array<int, int>, branch_ids?: array<int, int>|null, is_active?: bool}  $data
     */
    public function handle(CompanyUser $membership, array $data, User $actor): CompanyUser
    {
        $companyId = $membership->company_id;
        $isSelf = $membership->user_id === $actor->id;

        if ($isSelf && array_key_exists('is_active', $data) && ! $data['is_active']) {
            throw ValidationException::withMessages(['is_active' => ['You cannot deactivate your own membership.']]);
        }

        $newRoleIds = array_key_exists('role_ids', $data)
            ? collect($data['role_ids'])->map(fn ($id) => (int) $id)->unique()->values()
            : null;

        if ($newRoleIds !== null) {
            $currentRoleIds = DB::table('role_user')->where('user_id', $membership->user_id)->where('company_id', $companyId)->pluck('role_id');
            $this->guard->assertCanAssignRoles($actor, $companyId, $newRoleIds, $currentRoleIds);
        }

        if ($isSelf && $newRoleIds !== null) {
            $keepsUserManagement = Role::forTenant($companyId)
                ->whereIn('id', $newRoleIds)
                ->whereHas('permissions', fn ($q) => $q->where('slug', 'users.manage'))
                ->exists();

            if (! $keepsUserManagement) {
                throw ValidationException::withMessages([
                    'role_ids' => ['You cannot remove your own last role that allows managing users.'],
                ]);
            }
        }

        return DB::transaction(function () use ($membership, $data, $actor, $companyId, $isSelf, $newRoleIds): CompanyUser {
            $user = $membership->user;

            $oldRoleNames = $this->roleNames($user, $companyId);
            $old = [
                'roles' => $oldRoleNames,
                'branch_ids' => $membership->branch_ids,
                'is_active' => $membership->is_active,
            ];

            if ($newRoleIds !== null) {
                DB::table('role_user')->where('user_id', $user->id)->where('company_id', $companyId)->delete();
                $user->roles()->attach($newRoleIds->mapWithKeys(fn (int $id) => [$id => ['company_id' => $companyId]])->all());
            }

            if (array_key_exists('branch_ids', $data)) {
                $membership->branch_ids = $data['branch_ids'] === null ? null : array_values(array_map('intval', $data['branch_ids']));
            }

            if (array_key_exists('is_active', $data)) {
                $membership->is_active = (bool) $data['is_active'];
            }

            $membership->save();

            $new = [
                'roles' => $this->roleNames($user, $companyId),
                'branch_ids' => $membership->branch_ids,
                'is_active' => $membership->is_active,
            ];

            [$oldDiff, $newDiff] = AuditLogger::diff($old, $new);

            $this->audit->log('users.membership_updated', $user, $oldDiff, $newDiff, [
                'membership_id' => $membership->id,
            ], companyId: $companyId);

            if ($isSelf) {
                $actor->forgetPermissionCache();
            }

            return $membership;
        });
    }

    /**
     * @return array<int, string>
     */
    private function roleNames(User $user, int $companyId): array
    {
        return Role::query()
            ->join('role_user', 'role_user.role_id', '=', 'roles.id')
            ->where('role_user.user_id', $user->id)
            ->where('role_user.company_id', $companyId)
            ->orderBy('roles.name')
            ->pluck('roles.name')
            ->all();
    }
}
