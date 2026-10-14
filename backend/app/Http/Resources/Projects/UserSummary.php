<?php

namespace App\Http\Resources\Projects;

use App\Models\User;

/**
 * Minimal user shape used by project/task payloads. Load users with
 * `:id,name,avatar_path` so no extra columns are read.
 */
final class UserSummary
{
    /** Columns to select when eager-loading a user for UserSummary::from(). */
    public const COLUMNS = 'id,name,avatar_path';

    /**
     * @return array{id: int, name: string, avatar_url: ?string}|null
     */
    public static function from(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar_url' => $user->avatarUrl(),
        ];
    }
}
