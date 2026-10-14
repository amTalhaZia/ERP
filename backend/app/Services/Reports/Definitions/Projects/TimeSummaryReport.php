<?php

namespace App\Services\Reports\Definitions\Projects;

use App\Services\Reports\Definitions\QueryReportDefinition;
use App\Services\Reports\ReportContext;
use App\Support\Money\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Logged hours per project and person for a period, with billable value
 * (billable hours × hourly rate).
 */
class TimeSummaryReport extends QueryReportDefinition
{
    public function key(): string
    {
        return 'projects.time_summary';
    }

    public function module(): string
    {
        return 'projects';
    }

    public function name(): string
    {
        return 'Time summary';
    }

    public function description(): string
    {
        return 'Hours logged per project and team member, split into billable and non-billable.';
    }

    public function permissions(): array
    {
        return ['projects.view'];
    }

    public function params(): array
    {
        return [self::dateParam('from', 'From'), self::dateParam('to', 'To'), self::branchParam()];
    }

    public function defaults(ReportContext $context): array
    {
        return ['from' => $context->monthStart(), 'to' => $context->today()];
    }

    public function columns(array $params): array
    {
        return [
            self::col('project_number', 'Project no.'),
            self::col('project', 'Project'),
            self::col('member', 'Member'),
            self::col('entries', 'Entries', 'number'),
            self::col('hours', 'Hours', 'number'),
            self::col('billable_hours', 'Billable hours', 'number'),
            self::col('billable_amount', 'Billable amount', 'money'),
        ];
    }

    protected function query(ReportContext $context, array $params): Builder
    {
        return DB::table('time_entries as t')
            ->join('projects as p', 'p.id', '=', 't.project_id')
            ->leftJoin('users as u', 'u.id', '=', 't.user_id')
            ->where('t.company_id', $context->companyId())
            ->whereNull('p.deleted_at')
            ->whereBetween('t.date', [$params['from'], $params['to']])
            ->when($params['branch_id'] ?? null, fn (Builder $q, int $branchId) => $q->where('p.branch_id', $branchId))
            ->groupBy('p.id', 'p.number', 'p.name', 'u.id', 'u.name')
            ->orderBy('p.name')->orderBy('p.id')->orderBy('u.name')
            ->select(['p.number as project_number', 'p.name as project', 'u.name as member'])
            ->selectRaw('COUNT(*) as entries, COALESCE(SUM(t.hours), 0) as hours')
            ->selectRaw('COALESCE(SUM(CASE WHEN t.is_billable = 1 THEN t.hours ELSE 0 END), 0) as billable_hours')
            ->selectRaw('COALESCE(SUM(CASE WHEN t.is_billable = 1 THEN t.hours * COALESCE(t.hourly_rate, 0) ELSE 0 END), 0) as billable_amount');
    }

    protected function map(object $row): array
    {
        return [
            'project_number' => $row->project_number,
            'project' => $row->project,
            'member' => $row->member,
            'entries' => (int) $row->entries,
            'hours' => number_format((float) $row->hours, 2, '.', ''),
            'billable_hours' => number_format((float) $row->billable_hours, 2, '.', ''),
            'billable_amount' => Money::normalize((string) $row->billable_amount),
        ];
    }
}
