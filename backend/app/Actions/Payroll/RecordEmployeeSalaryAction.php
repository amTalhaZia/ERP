<?php

namespace App\Actions\Payroll;

use App\Enums\Payroll\PayFrequency;
use App\Models\HR\Employee;
use App\Models\Payroll\EmployeeSalary;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Appends a salary to an employee's history. An earlier open-ended salary is
 * closed the day before the new one takes effect, so at most one row is in
 * force on any date.
 */
class RecordEmployeeSalaryAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Employee $employee, array $data, ?int $userId): EmployeeSalary
    {
        return DB::transaction(function () use ($employee, $data, $userId): EmployeeSalary {
            $from = CarbonImmutable::parse($data['effective_from']);

            EmployeeSalary::query()
                ->where('employee_id', $employee->id)
                ->where('effective_from', '<', $from->toDateString())
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $from->toDateString()))
                ->lockForUpdate()
                ->get()
                ->each(fn (EmployeeSalary $previous) => $previous->update(['effective_to' => $from->subDay()->toDateString()]));

            $salary = EmployeeSalary::query()->create([
                'employee_id' => $employee->id,
                'salary_structure_id' => $data['salary_structure_id'] ?? null,
                'base_salary' => Money::normalize($data['base_salary']),
                'currency' => strtoupper($data['currency']),
                'effective_from' => $from->toDateString(),
                'effective_to' => $data['effective_to'] ?? null,
                'pay_frequency' => PayFrequency::Monthly,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            foreach ((array) ($data['components'] ?? []) as $row) {
                $salary->components()->create([
                    'company_id' => $salary->company_id,
                    'pay_component_id' => (int) $row['pay_component_id'],
                    'amount' => isset($row['amount']) ? Money::normalize($row['amount']) : null,
                    'rate' => isset($row['rate']) ? Money::normalize($row['rate']) : null,
                ]);
            }

            return $salary->load(['structure:id,name', 'components.component', 'creator:id,name']);
        });
    }
}
