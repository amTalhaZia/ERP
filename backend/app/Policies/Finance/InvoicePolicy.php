<?php

namespace App\Policies\Finance;

use App\Models\Finance\Invoice;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

/**
 * Who may attempt an operation. State refusals (editing a posted invoice,
 * voiding a paid one) are 409s raised by the actions, not policy denials.
 */
class InvoicePolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'invoices.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->canOn($user, 'invoices.view', $invoice) && $this->branchAccessible($invoice->branch_id);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'invoices.create');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->canOn($user, 'invoices.update', $invoice) && $this->branchAccessible($invoice->branch_id);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->canOn($user, 'invoices.delete', $invoice) && $this->branchAccessible($invoice->branch_id);
    }

    public function submit(User $user, Invoice $invoice): bool
    {
        return ($this->canOn($user, 'invoices.create', $invoice) || $this->canOn($user, 'invoices.update', $invoice))
            && $this->branchAccessible($invoice->branch_id);
    }

    public function approve(User $user, Invoice $invoice): bool
    {
        return $this->canOn($user, 'invoices.approve', $invoice) && $this->branchAccessible($invoice->branch_id);
    }

    public function void(User $user, Invoice $invoice): bool
    {
        return $this->approve($user, $invoice);
    }
}
