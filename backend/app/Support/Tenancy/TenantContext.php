<?php

namespace App\Support\Tenancy;

use App\Models\Company;
use App\Models\CompanyUser;

/**
 * Request-scoped, trusted company context. Populated only by
 * ResolveCompanyContext after membership has been verified, never from
 * client input directly.
 */
class TenantContext
{
    private ?Company $company = null;

    private ?CompanyUser $membership = null;

    public function set(Company $company, ?CompanyUser $membership): void
    {
        $this->company = $company;
        $this->membership = $membership;
    }

    public function clear(): void
    {
        $this->company = null;
        $this->membership = null;
    }

    public function has(): bool
    {
        return $this->company !== null;
    }

    public function company(): ?Company
    {
        return $this->company;
    }

    public function id(): ?int
    {
        return $this->company?->id;
    }

    public function membership(): ?CompanyUser
    {
        return $this->membership;
    }

    /**
     * Branch ids the current user may see, or null for all branches.
     *
     * @return array<int>|null
     */
    public function allowedBranchIds(): ?array
    {
        return $this->membership?->branch_ids;
    }

    public function canAccessBranch(?int $branchId): bool
    {
        $allowed = $this->allowedBranchIds();

        return $allowed === null || $branchId === null || in_array($branchId, $allowed, true);
    }
}
