<?php

namespace App\Policies\Finance;

use App\Models\Finance\JournalEntry;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

/**
 * State rules (draft-only edits, open period…) are NOT here: they are business
 * refusals answered with 409 by the actions. Policies only decide "may this
 * user, in this tenant, attempt the operation".
 */
class JournalEntryPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'journals.view');
    }

    public function view(User $user, JournalEntry $entry): bool
    {
        return $this->canOn($user, 'journals.view', $entry) && $this->branchAccessible($entry->branch_id);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'journals.create');
    }

    public function update(User $user, JournalEntry $entry): bool
    {
        return $this->canOn($user, 'journals.create', $entry) && $this->branchAccessible($entry->branch_id);
    }

    public function delete(User $user, JournalEntry $entry): bool
    {
        return $this->canOn($user, 'journals.create', $entry) && $this->branchAccessible($entry->branch_id);
    }

    public function post(User $user, JournalEntry $entry): bool
    {
        return $this->canOn($user, 'journals.post', $entry) && $this->branchAccessible($entry->branch_id);
    }

    public function reverse(User $user, JournalEntry $entry): bool
    {
        return $this->canOn($user, 'journals.post', $entry) && $this->branchAccessible($entry->branch_id);
    }
}
