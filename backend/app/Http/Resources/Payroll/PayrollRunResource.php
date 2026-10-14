<?php

namespace App\Http\Resources\Payroll;

use App\Models\Payroll\PayrollRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PayrollRun */
class PayrollRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = fn (string $relation) => $this->whenLoaded($relation, fn () => $this->{$relation} ? ['id' => $this->{$relation}->id, 'name' => $this->{$relation}->name] : null);

        return [
            'id' => $this->id,
            'number' => $this->number,
            'name' => $this->name,
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'payment_date' => $this->payment_date?->toDateString(),
            'currency' => $this->currency,
            'status' => $this->status->value,
            'branch_id' => $this->branch_id,
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? ['id' => $this->branch->id, 'name' => $this->branch->name] : null),
            'department_id' => $this->department_id,
            'department' => $this->whenLoaded('department', fn () => $this->department ? ['id' => $this->department->id, 'name' => $this->department->name] : null),
            'totals' => [
                'gross' => (string) $this->total_gross,
                'tax' => (string) $this->total_tax,
                'deductions' => (string) $this->total_deductions,
                'employer_contributions' => (string) $this->total_employer_contributions,
                'net' => (string) $this->total_net,
                'employee_count' => $this->employee_count,
            ],
            'calculated_at' => $this->calculated_at?->toJSON(),
            'created_by' => $user('creator'),
            'approved_by' => $user('approver'),
            'approved_at' => $this->approved_at?->toJSON(),
            'finalized_by' => $user('finalizer'),
            'finalized_at' => $this->finalized_at?->toJSON(),
            'paid_at' => $this->paid_at?->toJSON(),
            'bank_account_id' => $this->bank_account_id,
            'cancelled_at' => $this->cancelled_at?->toJSON(),
            'journal_entry_id' => $this->journal_entry_id,
            'payment_journal_entry_id' => $this->payment_journal_entry_id,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
