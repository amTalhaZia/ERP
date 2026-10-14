<?php

namespace App\Http\Resources\Access;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuditLog */
class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'company' => $this->whenLoaded('company', fn () => $this->company
                ? ['id' => $this->company->id, 'name' => $this->company->name]
                : null),
            'actor' => $this->whenLoaded('actor', fn () => $this->actor
                ? ['id' => $this->actor->id, 'name' => $this->actor->name]
                : ($this->actor_name ? ['id' => null, 'name' => $this->actor_name] : null)),
            'actor_name' => $this->actor_name,
            'action' => $this->action,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'entity_label' => $this->entity_label,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'context' => $this->context,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at?->toJSON(),
        ];
    }
}
