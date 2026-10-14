<?php

namespace App\Http\Resources\Finance;

use App\Models\Finance\JournalLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin JournalLine */
class JournalLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'line_no' => $this->line_no,
            'account_id' => $this->account_id,
            'account' => $this->whenLoaded('account', fn () => ['id' => $this->account->id, 'code' => $this->account->code, 'name' => $this->account->name]),
            'description' => $this->description,
            'debit' => (string) $this->debit,
            'credit' => (string) $this->credit,
            'branch_id' => $this->branch_id,
            'partner_type' => $this->partner_type,
            'partner_id' => $this->partner_id,
            'tax_rate_id' => $this->tax_rate_id,
        ];
    }
}
