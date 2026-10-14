<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

/** Read-only: audit logs have no create/update/delete abilities by design. */
class AuditLogPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'audit.view');
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $this->canOn($user, 'audit.view', $auditLog);
    }
}
