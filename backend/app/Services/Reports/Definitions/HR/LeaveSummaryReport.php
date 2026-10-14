<?php

namespace App\Services\Reports\Definitions\HR;

use App\Services\Reports\Definitions\QueryReportDefinition;
use App\Services\Reports\ReportContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Leave requests starting in a period per employee and leave type.
 */
class LeaveSummaryReport extends QueryReportDefinition
{
    public function key(): string
    {
        return 'hr.leave_summary';
    }

    public function module(): string
    {
        return 'hr';
    }

    public function name(): string
    {
        return 'Leave summary';
    }

    public function description(): string
    {
        return 'Approved and pending leave days per employee and leave type for a period.';
    }

    public function permissions(): array
    {
        return ['leave.view', 'leave.manage'];
    }

    public function params(): array
    {
        return [self::dateParam('from', 'From'), self::dateParam('to', 'To'), self::branchParam()];
    }

    public function defaults(ReportContext $context): array
    {
        return ['from' => substr($context->today(), 0, 4).'-01-01', 'to' => $context->today()];
    }

    public function columns(array $params): array
    {
        return [
            self::col('employee_code', 'Code'),
            self::col('employee', 'Employee'),
            self::col('leave_type', 'Leave type'),
            self::col('approved_requests', 'Approved requests', 'number'),
            self::col('approved_days', 'Approved days', 'number'),
            self::col('pending_requests', 'Pending requests', 'number'),
            self::col('pending_days', 'Pending days', 'number'),
        ];
    }

    protected function query(ReportContext $context, array $params): Builder
    {
        return DB::table('leave_requests as lr')
            ->join('employees as e', 'e.id', '=', 'lr.employee_id')
            ->join('leave_types as lt', 'lt.id', '=', 'lr.leave_type_id')
            ->where('lr.company_id', $context->companyId())
            ->whereIn('lr.status', ['approved', 'pending'])
            ->whereBetween('lr.start_date', [$params['from'], $params['to']])
            ->when($params['branch_id'] ?? null, fn (Builder $q, int $branchId) => $q->where('e.branch_id', $branchId))
            ->groupBy('e.id', 'e.employee_code', 'e.first_name', 'e.last_name', 'lt.id', 'lt.name')
            ->orderBy('e.first_name')->orderBy('e.last_name')->orderBy('e.id')->orderBy('lt.name')
            ->select(['e.employee_code', 'e.first_name', 'e.last_name', 'lt.name as leave_type'])
            ->selectRaw("SUM(lr.status = 'approved') as approved_requests, COALESCE(SUM(CASE WHEN lr.status = 'approved' THEN lr.days END), 0) as approved_days")
            ->selectRaw("SUM(lr.status = 'pending') as pending_requests, COALESCE(SUM(CASE WHEN lr.status = 'pending' THEN lr.days END), 0) as pending_days");
    }

    protected function map(object $row): array
    {
        return [
            'employee_code' => $row->employee_code,
            'employee' => trim($row->first_name.' '.$row->last_name),
            'leave_type' => $row->leave_type,
            'approved_requests' => (int) $row->approved_requests,
            'approved_days' => number_format((float) $row->approved_days, 1, '.', ''),
            'pending_requests' => (int) $row->pending_requests,
            'pending_days' => number_format((float) $row->pending_days, 1, '.', ''),
        ];
    }
}
