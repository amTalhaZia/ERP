<?php

namespace App\Http\Resources\Platform;

use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class PlatformUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatarUrl(),
            'is_active' => $this->is_active,
            'platform_role' => $this->platform_role,
            'is_platform_admin' => $this->isPlatformAdmin(),
            'email_verified_at' => $this->email_verified_at?->toJSON(),
            'two_factor_enabled' => $this->hasTwoFactorEnabled(),
            'last_login_at' => $this->last_login_at?->toJSON(),
            'deactivated_at' => $this->deactivated_at?->toJSON(),
            'memberships_count' => $this->whenCounted('memberships'),
            'memberships' => $this->whenLoaded('memberships', fn () => $this->memberships
                ->map(fn (CompanyUser $m) => [
                    'company_id' => $m->company_id,
                    'company_name' => $m->relationLoaded('company') ? $m->company?->name : null,
                    'roles' => $this->relationLoaded('roles')
                        ? $this->roles->filter(fn ($r) => (int) $r->pivot->company_id === (int) $m->company_id)
                            ->map(fn ($r) => ['slug' => $r->slug, 'name' => $r->name])->values()
                        : [],
                    'is_active' => $m->is_active,
                    'is_default' => $m->is_default,
                    'joined_at' => $m->joined_at?->toJSON(),
                ])
                ->values()),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
