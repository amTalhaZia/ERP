<?php

namespace App\Http\Resources\Sales;

use App\Models\Sales\PipelineStage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PipelineStage */
class PipelineStageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'probability' => $this->probability,
            'position' => $this->position,
            'is_won' => $this->is_won,
            'is_lost' => $this->is_lost,
            'color' => $this->color,
            'opportunities_count' => $this->whenCounted('opportunities'),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
