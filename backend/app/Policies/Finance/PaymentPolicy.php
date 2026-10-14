<?php

namespace App\Policies\Finance;

use App\Models\Finance\Payment;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class PaymentPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'payments.view');
    }

    public function view(User $user, Payment $payment): bool
    {
        return $this->canOn($user, 'payments.view', $payment) && $this->branchAccessible($payment->branch_id);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'payments.create');
    }

    public function void(User $user, Payment $payment): bool
    {
        return $this->canOn($user, 'payments.create', $payment) && $this->branchAccessible($payment->branch_id);
    }
}
