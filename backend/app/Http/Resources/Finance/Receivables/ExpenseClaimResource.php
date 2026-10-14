<?php

namespace App\Http\Resources\Finance\Receivables;

use App\Models\Finance\ExpenseClaim;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ExpenseClaim */
class ExpenseClaimResource extends JsonResource
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
            'employee_id' => $this->employee_id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn () => $this->user ? ['id' => $this->user->id, 'name' => $this->user->name] : null),
            'claim_date' => $this->claim_date->toDateString(),
            'currency' => $this->currency,
            'status' => $this->status->value,
            'subtotal' => (string) $this->subtotal,
            'tax_total' => (string) $this->tax_total,
            'total' => (string) $this->total,
            'amount_paid' => (string) $this->amount_paid,
            'balance_due' => (string) $this->balance_due,
            'notes' => $this->notes,
            'branch_id' => $this->branch_id,
            'submitted_at' => $this->submitted_at?->toJSON(),
            'approver_id' => $this->approver_id,
            'approver' => $this->whenLoaded('approver', fn () => $this->approver ? ['id' => $this->approver->id, 'name' => $this->approver->name] : null),
            'approved_at' => $this->approved_at?->toJSON(),
            'rejection_reason' => $this->rejection_reason,
            'journal_entry_id' => $this->journal_entry_id,
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line) => [
                'id' => $line->id,
                'line_no' => $line->line_no,
                'expense_date' => $line->expense_date->toDateString(),
                'category' => $line->category,
                'account_id' => $line->account_id,
                'description' => $line->description,
                'amount' => (string) $line->amount,
                'tax_rate_id' => $line->tax_rate_id,
                'tax_rate' => (string) $line->tax_rate,
                'tax_amount' => (string) $line->tax_amount,
                'line_total' => (string) $line->line_total,
                'has_receipt' => $line->receipt_path !== null,
                'receipt_name' => $line->receipt_original_name,
            ])->all()),
            'lines_count' => $this->whenCounted('lines'),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
