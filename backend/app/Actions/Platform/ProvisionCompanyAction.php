<?php

namespace App\Actions\Platform;

use App\Actions\Company\CreateCompanyAction;
use App\Models\Company;
use App\Models\User;
use App\Notifications\UserInvitedNotification;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Platform-side company creation: finds or creates the owner account (random,
 * never-disclosed password + reset link for new accounts), then delegates to
 * CreateCompanyAction for the company, head office, roles and owner membership.
 */
class ProvisionCompanyAction
{
    public function __construct(
        private readonly CreateCompanyAction $createCompany,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array{name: string, email: string}  $owner
     */
    public function handle(array $attributes, array $owner, ?User $actor = null): Company
    {
        $isNewAccount = false;
        $ownerUser = null;

        $company = DB::transaction(function () use ($attributes, $owner, &$isNewAccount, &$ownerUser): Company {
            $email = mb_strtolower(trim($owner['email']));
            $ownerUser = User::query()->where('email', $email)->first();

            if ($ownerUser === null) {
                $isNewAccount = true;
                $ownerUser = User::query()->create([
                    'name' => $owner['name'],
                    'email' => $email,
                    'password' => Str::password(40),
                    'is_active' => true,
                ]);
            }

            $company = $this->createCompany->handle($attributes, $ownerUser);

            $this->audit->log('platform.company_created', $company, null, [
                'name' => $company->name,
                'code' => $company->code,
                'owner_email' => $ownerUser->email,
            ], [
                'owner_id' => $ownerUser->id,
                'new_owner_account' => $isNewAccount,
            ], companyId: $company->id);

            return $company;
        });

        if ($isNewAccount) {
            Password::sendResetLink(['email' => $ownerUser->email]);
        }

        $ownerUser->notify(new UserInvitedNotification($company, $actor, $isNewAccount));

        return $company;
    }
}
