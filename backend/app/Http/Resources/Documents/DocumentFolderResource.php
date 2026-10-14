<?php

namespace App\Http\Resources\Documents;

use App\Models\Documents\DocumentFolder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DocumentFolder */
class DocumentFolderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'is_private' => $this->is_private,
            'owner_id' => $this->owner_id,
            'documents_count' => $this->whenCounted('documents'),
            'children_count' => $this->whenCounted('children'),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
