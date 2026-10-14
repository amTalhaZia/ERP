<?php

namespace App\Http\Resources\HR;

use App\Models\HR\EmployeeDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmployeeDocument */
class EmployeeDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'employee_id' => $this->employee_id,
            'title' => $this->title,
            'category' => $this->category?->value,
            'mime' => $this->mime,
            'size_bytes' => $this->size_bytes,
            'expires_at' => $this->expires_at?->toDateString(),
            'is_expired' => $this->expires_at !== null && $this->expires_at->isPast(),
            'is_confidential' => $this->is_confidential,
            'uploaded_by' => $this->uploaded_by,
            'uploader' => $this->whenLoaded('uploader', fn () => $this->uploader ? ['id' => $this->uploader->id, 'name' => $this->uploader->name] : null),
            'download_url' => route('api.v1.hr.employee-documents.download', $this->id),
            'created_at' => $this->created_at?->toJSON(),
        ];
    }
}
