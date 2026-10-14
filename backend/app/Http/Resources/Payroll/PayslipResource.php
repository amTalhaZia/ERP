<?php

namespace App\Http\Resources\Payroll;

use App\Models\Payroll\Payslip;
use App\Models\Payroll\PayslipLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payslip */
class PayslipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'payroll_run_id' => $this->payroll_run_id,
            'run' => $this->whenLoaded('run', fn () => [
                'id' => $this->run->id,
                'number' => $this->run->number,
                'name' => $this->run->name,
                'period_start' => $this->run->period_start->toDateString(),
                'period_end' => $this->run->period_end->toDateString(),
                'payment_date' => $this->run->payment_date->toDateString(),
            ]),
            'employee' => [
                'id' => $this->employee_id,
                'code' => $this->employee_code,
                'name' => $this->employee_name,
                'department' => $this->department_name,
                'designation' => $this->designation_title,
                'bank_name' => $this->bank_name,
                'bank_account' => $this->bank_account_last4 === null ? null : '••••'.$this->bank_account_last4,
            ],
            'currency' => $this->currency,
            'base_salary' => (string) $this->base_salary,
            'working_days' => (string) $this->working_days,
            'unpaid_days' => (string) $this->unpaid_days,
            'paid_days' => (string) $this->paid_days,
            'earned_base' => (string) $this->earned_base,
            'gross' => (string) $this->gross,
            'taxable' => (string) $this->taxable,
            'tax' => (string) $this->tax,
            'total_deductions' => (string) $this->total_deductions,
            'employer_contributions' => (string) $this->employer_contributions,
            'net' => (string) $this->net,
            'warnings' => $this->warnings ?? [],
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn (PayslipLine $line) => [
                'code' => $line->code,
                'name' => $line->name,
                'kind' => $line->kind->value,
                'calculation' => $line->calculation,
                'rate' => $line->rate === null ? null : (string) $line->rate,
                'amount' => (string) $line->amount,
                'is_taxable' => $line->is_taxable,
            ])->values()),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
