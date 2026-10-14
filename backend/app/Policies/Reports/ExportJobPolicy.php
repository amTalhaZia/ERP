<?php

namespace App\Policies\Reports;

use App\Models\Reports\ExportJob;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

/**
 * Export files are private to the user who requested them.
 */
class ExportJobPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'reports.export');
    }

    public function view(User $user, ExportJob $exportJob): bool
    {
        return $this->sameTenant($exportJob) && (int) $exportJob->user_id === (int) $user->id;
    }

    public function download(User $user, ExportJob $exportJob): bool
    {
        return $this->view($user, $exportJob);
    }
}
