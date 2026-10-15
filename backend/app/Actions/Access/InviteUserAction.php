<?php

namespace App\Actions\Access;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Role;
use App\Models\User;
use App\Notifications\UserInvitedNotification;
use App\Support\Access\PrivilegeEscalationGuard;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Adds a user to a company. Existing accounts (matched by e-mail) simply get a
 * membership; unknown e-mails get a new account with a random, never-disclosed
 * password and a password-reset link so they choose their own.
 */
class InviteUserAction
{
    public function __construct(private readonly AuditLogger $audit, private readonly PrivilegeEscalationGuard $guard) {}

    /**
     * @param  array{name: string, email: string, role_ids: array<int, int>, branch_ids?: array<int, int>|null}  $data
     */
    public function handle(Company $company, array $data, ?User $invitedBy = null): CompanyUser
    {
        // Members may not hand out roles beyond their own access (or the owner role).
        if ($invitedBy !== null) {
            $this->guard->assertCanAssignRoles($invitedBy, $company->id, $data['role_ids']);
        }

        $isNewAccount = false;

        $membership = DB::transaction(function () use ($company, $data, &$isNewAccount): CompanyUser {
            $user = User::query()->where('email', $data['email'])->first();

            if ($user === null) {
                $isNewAccount = true;
                $user = User::query()->create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Str::password(40),
                    'is_active' => true,
                ]);
            } elseif (CompanyUser::query()->where('company_id', $company->id)->where('user_id', $user->id)->exists()) {
                throw new ConflictHttpException('This user is already a member of the company.');
            }

            $membership = CompanyUser::query()->create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'is_default' => ! $user->memberships()->exists(),
                'is_active' => true,
                'branch_ids' => $data['branch_ids'] ?? null,
                'joined_at' => now(),
            ]);

            $roleIds = collect($data['role_ids'])->map(fn ($id) => (int) $id)->unique()->values();
            $user->roles()->attach($roleIds->mapWithKeys(fn (int $id) => [$id => ['company_id' => $company->id]])->all());

            $roleNames = Role::forTenant($company->id)->whereIn('id', $roleIds)->orderBy('name')->pluck('name')->all();

            $this->audit->log('users.invited', $user, null, [
                'roles' => $roleNames,
                'branch_ids' => $membership->branch_ids,
            ], [
                'new_account' => $isNewAccount,
                'membership_id' => $membership->id,
            ], companyId: $company->id);

            return $membership->setRelation('user', $user);
        });

        if ($isNewAccount) {
            Password::sendResetLink(['email' => $membership->user->email]);
        }

        $membership->user->notify(new UserInvitedNotification($company, $invitedBy, $isNewAccount));

        return $membership;
    }
}
