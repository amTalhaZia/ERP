<?php

namespace App\Http\Resources\Finance;

use App\Models\Finance\FinancialPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FinancialPeriod */
class FinancialPeriodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'financial_year_id' => $this->financial_year_id,
            'name' => $this->name,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'status' => $this->status->value,
            'closed_by' => $this->whenLoaded('closedBy', fn () => $this->closedBy ? ['id' => $this->closedBy->id, 'name' => $this->closedBy->name] : null),
            'closed_at' => $this->closed_at?->toJSON(),
            'financial_year' => $this->whenLoaded('financialYear', fn () => ['id' => $this->financialYear->id, 'name' => $this->financialYear->name]),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
