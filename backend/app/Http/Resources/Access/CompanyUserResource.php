<?php

namespace App\Http\Resources\Access;

use App\Models\CompanyUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A company member: user-level identity plus the membership for the current
 * tenant. `id` is the user id (the route key); `roles` is attached by the
 * controller via `setRelation('roles', ...)` for the current company only.
 *
 * @mixin CompanyUser
 */
class CompanyUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->user_id,
            'membership_id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'phone' => $this->user->phone,
            'avatar_url' => $this->user->avatarUrl(),
            'user_is_active' => $this->user->is_active,
            'email_verified_at' => $this->user->email_verified_at?->toJSON(),
            'last_login_at' => $this->user->last_login_at?->toJSON(),
            'is_active' => $this->is_active,
            'is_default' => $this->is_default,
            'branch_ids' => $this->branch_ids,
            'roles' => $this->whenLoaded('roles', fn () => collect($this->roles)->values()),
            'joined_at' => $this->joined_at?->toJSON(),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
