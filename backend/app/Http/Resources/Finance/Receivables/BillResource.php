<?php

namespace App\Http\Resources\Finance\Receivables;

use App\Models\Finance\Bill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Bill */
class BillResource extends JsonResource
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
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->whenLoaded('partner'),
            'purchase_order_id' => $this->purchase_order_id,
            'supplier_reference' => $this->supplier_reference,
            'bill_date' => $this->bill_date->toDateString(),
            'due_date' => $this->due_date->toDateString(),
            'currency' => $this->currency,
            'status' => $this->status->value,
            'is_overdue' => $this->isOverdue(),
            'subtotal' => (string) $this->subtotal,
            'discount_total' => (string) $this->discount_total,
            'tax_total' => (string) $this->tax_total,
            'total' => (string) $this->total,
            'amount_paid' => (string) $this->amount_paid,
            'balance_due' => (string) $this->balance_due,
            'notes' => $this->notes,
            'branch_id' => $this->branch_id,
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? ['id' => $this->branch->id, 'name' => $this->branch->name] : null),
            'journal_entry_id' => $this->journal_entry_id,
            'created_by' => $this->created_by,
            'approved_by' => $this->approved_by,
            'approver' => $this->whenLoaded('approver', fn () => $this->approver ? ['id' => $this->approver->id, 'name' => $this->approver->name] : null),
            'approved_at' => $this->approved_at?->toJSON(),
            'voided_at' => $this->voided_at?->toJSON(),
            'void_reason' => $this->void_reason,
            'lines' => DocumentLineResource::collection($this->whenLoaded('lines')),
            'lines_count' => $this->whenCounted('lines'),
            'payments' => $this->whenLoaded('allocations', fn () => $this->allocations->map(fn ($allocation) => [
                'allocation_id' => $allocation->id,
                'payment_id' => $allocation->payment_id,
                'payment_number' => $allocation->payment?->number,
                'payment_date' => $allocation->payment?->payment_date?->toDateString(),
                'method' => $allocation->payment?->method?->value,
                'amount' => (string) $allocation->amount,
                'status' => $allocation->payment?->status?->value,
            ])->values()->all()),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
