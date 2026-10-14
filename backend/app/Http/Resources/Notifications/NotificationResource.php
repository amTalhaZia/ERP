<?php

namespace App\Http\Resources\Notifications;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/** @mixin DatabaseNotification */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = (array) $this->data;

        return [
            'id' => $this->id,
            'type' => $data['type'] ?? class_basename($this->type),
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? null,
            'url' => $data['url'] ?? null,
            'company_id' => $data['company_id'] ?? null,
            'meta' => $data['meta'] ?? [],
            'is_read' => $this->read_at !== null,
            'read_at' => $this->read_at?->toJSON(),
            'created_at' => $this->created_at?->toJSON(),
        ];
    }
}
