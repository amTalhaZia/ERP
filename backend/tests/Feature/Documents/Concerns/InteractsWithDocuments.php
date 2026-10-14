<?php

namespace Tests\Feature\Documents\Concerns;

use App\Models\Company;
use App\Models\User;

/**
 * Shared helpers for Document Management feature tests. Arrange
 * cross-company fixtures BEFORE the first request (tenant context persists).
 */
trait InteractsWithDocuments
{
    /** @var array<int, string> */
    protected array $documentManagerPermissions = ['documents.view', 'documents.manage'];

    protected function switchTo(User $user, Company $company): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAsIn($user, $company);
    }

    /** First role id the user holds in the company. */
    protected function roleIdOf(User $user, Company $company): int
    {
        return (int) \DB::table('role_user')->where('user_id', $user->id)->where('company_id', $company->id)->value('role_id');
    }
}
