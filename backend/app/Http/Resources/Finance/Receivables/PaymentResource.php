<?php

namespace App\Http\Resources\Finance\Receivables;

use App\Models\Finance\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
class PaymentResource extends JsonResource
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
            'type' => $this->type->value,
            'partner_type' => $this->partner_type->value,
            'partner_id' => $this->partner_id,
            'partner' => $this->whenLoaded('partner'),
            'payment_date' => $this->payment_date->toDateString(),
            'method' => $this->method->value,
            'bank_account_id' => $this->bank_account_id,
            'bank_account' => $this->whenLoaded('bankAccount', fn () => $this->bankAccount ? [
                'id' => $this->bankAccount->id,
                'name' => $this->bankAccount->name,
                'account_number' => $this->bankAccount->maskedAccountNumber(),
            ] : null),
            'amount' => (string) $this->amount,
            'currency' => $this->currency,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'status' => $this->status->value,
            'branch_id' => $this->branch_id,
            'journal_entry_id' => $this->journal_entry_id,
            'created_by' => $this->created_by,
            'voided_at' => $this->voided_at?->toJSON(),
            'void_reason' => $this->void_reason,
            'allocations' => $this->whenLoaded('allocations', fn () => $this->allocations->map(fn ($allocation) => [
                'id' => $allocation->id,
                'type' => $allocation->allocatable_type,
                'document_id' => $allocation->allocatable_id,
                'document_number' => $allocation->getAttributes()['document_number'] ?? null,
                'amount' => (string) $allocation->amount,
            ])->all()),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
