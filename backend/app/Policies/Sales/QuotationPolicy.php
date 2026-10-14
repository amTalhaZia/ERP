<?php

namespace App\Policies\Sales;

use App\Models\Sales\Quotation;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class QuotationPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'quotations.view');
    }

    public function view(User $user, Quotation $model): bool
    {
        return $this->canOn($user, 'quotations.view', $model);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'quotations.manage');
    }

    public function update(User $user, Quotation $model): bool
    {
        return $this->canOn($user, 'quotations.manage', $model);
    }

    public function delete(User $user, Quotation $model): bool
    {
        return $this->canOn($user, 'quotations.manage', $model);
    }
}
