<?php

namespace App\Policies\Projects;

use App\Models\Projects\TimeEntry;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

/**
 * Users with `time_entries.manage` log and edit their own time; `projects.manage`
 * may edit anyone's and log on behalf of others. `projects.view` reads all.
 */
class TimeEntryPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['time_entries.manage', 'projects.view', 'projects.manage']);
    }

    public function view(User $user, TimeEntry $entry): bool
    {
        return $this->sameTenant($entry) && (
            $user->hasAnyPermission(['projects.view', 'projects.manage'])
            || ($entry->user_id === $user->id && $this->can($user, 'time_entries.manage'))
        );
    }

    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['time_entries.manage', 'projects.manage']);
    }

    public function update(User $user, TimeEntry $entry): bool
    {
        return $this->sameTenant($entry) && (
            $this->can($user, 'projects.manage')
            || ($entry->user_id === $user->id && $this->can($user, 'time_entries.manage'))
        );
    }

    public function delete(User $user, TimeEntry $entry): bool
    {
        return $this->update($user, $entry);
    }

    /** See everyone's entries in lists (otherwise only one's own). */
    public function viewAll(User $user): bool
    {
        return $user->hasAnyPermission(['projects.view', 'projects.manage']);
    }
}
