<?php

namespace App\Http\Resources\Finance;

use App\Models\Finance\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin JournalEntry */
class JournalEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'number' => $this->number,
            'entry_date' => $this->entry_date->toDateString(),
            'financial_period_id' => $this->financial_period_id,
            'period' => $this->whenLoaded('period', fn () => $this->period ? ['id' => $this->period->id, 'name' => $this->period->name, 'status' => $this->period->status->value] : null),
            'reference' => $this->reference,
            'memo' => $this->memo,
            'status' => $this->status->value,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'branch_id' => $this->branch_id,
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? ['id' => $this->branch->id, 'name' => $this->branch->name] : null),
            'total_debit' => (string) $this->total_debit,
            'total_credit' => (string) $this->total_credit,
            'is_balanced' => (string) $this->total_debit === (string) $this->total_credit,
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? ['id' => $this->creator->id, 'name' => $this->creator->name] : null),
            'posted_by' => $this->posted_by,
            'poster' => $this->whenLoaded('poster', fn () => $this->poster ? ['id' => $this->poster->id, 'name' => $this->poster->name] : null),
            'posted_at' => $this->posted_at?->toJSON(),
            'reverses_entry_id' => $this->reverses_entry_id,
            'reversed_by_entry_id' => $this->reversed_by_entry_id,
            'lines' => JournalLineResource::collection($this->whenLoaded('lines')),
            'lines_count' => $this->whenCounted('lines'),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
