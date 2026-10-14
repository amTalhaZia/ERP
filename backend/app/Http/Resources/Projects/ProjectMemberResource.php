<?php

namespace App\Http\Resources\Projects;

use App\Models\Projects\ProjectMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProjectMember */
class ProjectMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn () => UserSummary::from($this->user)),
            'role' => $this->role,
            'created_at' => $this->created_at?->toJSON(),
        ];
    }
}
