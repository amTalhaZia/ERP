<?php

namespace App\Policies\Projects;

use App\Models\Projects\TaskComment;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class TaskCommentPolicy
{
    use ChecksPermissions;

    /** Only the author may edit a comment. */
    public function update(User $user, TaskComment $comment): bool
    {
        return $this->sameTenant($comment) && $comment->user_id === $user->id;
    }

    /** The author, or anyone with projects.manage (moderation). */
    public function delete(User $user, TaskComment $comment): bool
    {
        return $this->sameTenant($comment)
            && ($comment->user_id === $user->id || $this->can($user, 'projects.manage'));
    }
}
