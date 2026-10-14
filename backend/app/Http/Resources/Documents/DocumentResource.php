<?php

namespace App\Http\Resources\Documents;

use App\Models\Documents\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Document */
class DocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category?->value,
            'tags' => $this->tags ?? [],
            'folder_id' => $this->folder_id,
            'folder' => $this->whenLoaded('folder', fn () => $this->folder ? ['id' => $this->folder->id, 'name' => $this->folder->name] : null),
            'documentable_type' => $this->documentable_type,
            'documentable_id' => $this->documentable_id,
            'visibility' => $this->visibility?->value,
            'allowed_role_ids' => $this->allowed_role_ids ?? [],
            'owner_id' => $this->owner_id,
            'owner' => $this->whenLoaded('owner', fn () => $this->owner ? ['id' => $this->owner->id, 'name' => $this->owner->name] : null),
            'current_version' => $this->whenLoaded('currentVersion', fn () => $this->currentVersion ? new DocumentVersionResource($this->currentVersion) : null),
            'versions' => DocumentVersionResource::collection($this->whenLoaded('versions')),
            'versions_count' => $this->whenCounted('versions'),
            'download_url' => '/api/v1/documents/'.$this->id.'/download',
            'retention_until' => $this->retention_until?->toDateString(),
            'is_archived' => $this->is_archived,
            'archived_at' => $this->archived_at?->toJSON(),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
