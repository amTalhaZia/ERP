<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;
use App\Support\Tenancy\TenantContext;

class CompanyPolicy
{
    use ChecksPermissions;

    private function isCurrent(Company $company): bool
    {
        return app(TenantContext::class)->id() === $company->id;
    }

    public function view(User $user, Company $company): bool
    {
        return $this->isCurrent($company) && $this->can($user, 'company.view');
    }

    public function update(User $user, Company $company): bool
    {
        return $this->isCurrent($company) && $this->can($user, 'company.settings.manage');
    }
}
