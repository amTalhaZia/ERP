<?php

namespace App\Policies\Documents;

use App\Models\Documents\Document;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

/**
 * Every record ability = same tenant + permission + Document::isVisibleTo()
 * (visibility, private folder, employee-document permission).
 */
class DocumentPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'documents.view');
    }

    public function view(User $user, Document $document): bool
    {
        return $this->canOn($user, 'documents.view', $document) && $document->isVisibleTo($user);
    }

    public function download(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    public function share(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'documents.manage');
    }

    public function update(User $user, Document $document): bool
    {
        return $this->canOn($user, 'documents.manage', $document) && $document->isVisibleTo($user);
    }

    public function addVersion(User $user, Document $document): bool
    {
        return $this->update($user, $document);
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->update($user, $document);
    }
}
