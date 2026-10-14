<?php

namespace App\Http\Resources\Sales\Concerns;

use App\Models\User;
use Illuminate\Http\Resources\MissingValue;

trait PresentsUsers
{
    /**
     * Minimal `{id, name}` for a loaded user relation (MissingValue when not loaded).
     *
     * @return array{id: int, name: string}|MissingValue|null
     */
    protected function userSummary(string $relation): array|MissingValue|null
    {
        return $this->whenLoaded($relation, function () use ($relation) {
            /** @var User|null $user */
            $user = $this->resource->getRelation($relation);

            return $user === null ? null : ['id' => $user->id, 'name' => $user->name];
        });
    }
}
