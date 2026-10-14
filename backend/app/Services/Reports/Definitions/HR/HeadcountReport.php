<?php

namespace App\Services\Reports\Definitions\HR;

use App\Services\Reports\Definitions\ReportDefinition;
use App\Services\Reports\ReportContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Employees on the payroll as of a date (hired on/before, not terminated by
 * then), grouped by department, branch or employment type.
 */
class HeadcountReport extends ReportDefinition
{
    public function key(): string
    {
        return 'hr.headcount';
    }

    public function module(): string
    {
        return 'hr';
    }

    public function name(): string
    {
        return 'Headcount';
    }

    public function description(): string
    {
        return 'Active headcount as of a date grouped by department, branch or employment type.';
    }

    public function permissions(): array
    {
        return ['employees.view'];
    }

    public function params(): array
    {
        return [
            self::dateParam('as_of', 'As of'),
            self::selectParam('group_by', 'Group by', ['department' => 'Department', 'branch' => 'Branch', 'employment_type' => 'Employment type']),
            self::branchParam(),
        ];
    }

    public function defaults(ReportContext $context): array
    {
        return ['as_of' => $context->today(), 'group_by' => 'department'];
    }

    public function columns(array $params): array
    {
        return [
            self::col('group', match ($params['group_by'] ?? 'department') {
                'branch' => 'Branch',
                'employment_type' => 'Employment type',
                default => 'Department',
            }),
            self::col('headcount', 'Headcount', 'number'),
        ];
    }

    protected function compute(ReportContext $context, array $params): array
    {
        $date = $params['as_of'];
        $query = DB::table('employees as emp')
            ->where('emp.company_id', $context->companyId())
            ->whereNull('emp.deleted_at')
            ->where(fn (Builder $q) => $q->whereNull('emp.hired_at')->orWhere('emp.hired_at', '<=', $date))
            ->where(fn (Builder $q) => $q->whereNull('emp.terminated_at')->orWhere('emp.terminated_at', '>', $date))
            ->where(fn (Builder $q) => $q->where('emp.status', '!=', 'terminated')->orWhereNotNull('emp.terminated_at'))
            ->when($params['branch_id'] ?? null, fn (Builder $q, int $branchId) => $q->where('emp.branch_id', $branchId));

        match ($params['group_by']) {
            'branch' => $query->leftJoin('branches as g', 'g.id', '=', 'emp.branch_id')
                ->groupBy('g.id', 'g.name')->selectRaw("COALESCE(g.name, 'Unassigned') as label, COUNT(*) as headcount"),
            'employment_type' => $query->groupBy('emp.employment_type')
                ->selectRaw("COALESCE(emp.employment_type, 'unspecified') as label, COUNT(*) as headcount"),
            default => $query->leftJoin('departments as g', 'g.id', '=', 'emp.department_id')
                ->groupBy('g.id', 'g.name')->selectRaw("COALESCE(g.name, 'Unassigned') as label, COUNT(*) as headcount"),
        };

        $rows = $query->orderByDesc('headcount')->orderBy('label')->get()
            ->map(fn (object $row) => ['group' => $row->label, 'headcount' => (int) $row->headcount])
            ->values()->all();

        return ['rows' => $rows, 'totals' => ['group' => 'Total', 'headcount' => array_sum(array_column($rows, 'headcount'))]];
    }
}
