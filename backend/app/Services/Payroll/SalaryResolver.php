<?php

namespace App\Services\Payroll;

use App\Models\Payroll\EmployeeSalary;
use App\Models\Payroll\PayComponent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Resolves the salary in force for a period and the effective component set
 * (structure components, then per-employee overrides/additions).
 */
class SalaryResolver
{
    /**
     * Latest salary with effective_from ≤ period end and (no end or end ≥ period start).
     *
     * @param  array<int, int>  $employeeIds
     * @return Collection<int, EmployeeSalary> keyed by employee_id
     */
    public function salariesFor(array $employeeIds, string $start, string $end): Collection
    {
        if ($employeeIds === []) {
            return collect();
        }

        return EmployeeSalary::query()
            ->with(['structure.components.component', 'components.component'])
            ->whereIn('employee_id', $employeeIds)
            ->where('effective_from', '<=', $end)
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $start))
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get()
            ->unique('employee_id')
            ->keyBy('employee_id');
    }

    /**
     * Active components for a salary, ordered by sort_order then code.
     * Override precedence: employee override → structure override → component default.
     *
     * @return list<array{component: PayComponent, amount: string, rate: string}>
     */
    public function componentsFor(EmployeeSalary $salary): array
    {
        $resolved = [];

        foreach ($salary->structure?->components ?? [] as $row) {
            $resolved[$row->pay_component_id] = $this->entry($row->component, $row->amount, $row->rate);
        }

        foreach ($salary->components as $row) {
            $resolved[$row->pay_component_id] = $this->entry($row->component, $row->amount, $row->rate);
        }

        $resolved = array_values(array_filter($resolved, fn (array $entry) => $entry['component']->is_active));

        usort($resolved, fn (array $a, array $b) => [$a['component']->sort_order, $a['component']->code]
            <=> [$b['component']->sort_order, $b['component']->code]);

        return $resolved;
    }

    /**
     * @return array{component: PayComponent, amount: string, rate: string}
     */
    private function entry(PayComponent $component, mixed $amount, mixed $rate): array
    {
        return [
            'component' => $component,
            'amount' => (string) ($amount ?? $component->default_amount),
            'rate' => (string) ($rate ?? $component->default_rate),
        ];
    }
}
