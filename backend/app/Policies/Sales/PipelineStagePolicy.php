<?php

namespace App\Policies\Sales;

use App\Models\Sales\PipelineStage;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class PipelineStagePolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'leads.view');
    }

    public function view(User $user, PipelineStage $model): bool
    {
        return $this->canOn($user, 'leads.view', $model);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'leads.manage');
    }

    public function update(User $user, PipelineStage $model): bool
    {
        return $this->canOn($user, 'leads.manage', $model);
    }

    public function delete(User $user, PipelineStage $model): bool
    {
        return $this->canOn($user, 'leads.manage', $model);
    }
}
