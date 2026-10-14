<?php

namespace App\Policies\Documents;

use App\Models\Documents\DocumentFolder;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class DocumentFolderPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'documents.view');
    }

    public function view(User $user, DocumentFolder $folder): bool
    {
        return $this->canOn($user, 'documents.view', $folder) && $folder->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'documents.manage');
    }

    public function update(User $user, DocumentFolder $folder): bool
    {
        return $this->canOn($user, 'documents.manage', $folder) && $folder->isVisibleTo($user);
    }

    public function delete(User $user, DocumentFolder $folder): bool
    {
        return $this->update($user, $folder);
    }
}
