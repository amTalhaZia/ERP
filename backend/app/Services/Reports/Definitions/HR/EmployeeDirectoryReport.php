<?php

namespace App\Services\Reports\Definitions\HR;

use App\Enums\HR\EmployeeStatus;
use App\Services\Reports\Definitions\QueryReportDefinition;
use App\Services\Reports\ReportContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Contact directory. Deliberately excludes sensitive columns (salary, bank,
 * national id, tax id, date of birth).
 */
class EmployeeDirectoryReport extends QueryReportDefinition
{
    public function key(): string
    {
        return 'hr.employee_directory';
    }

    public function module(): string
    {
        return 'hr';
    }

    public function name(): string
    {
        return 'Employee directory';
    }

    public function description(): string
    {
        return 'Employees with contact details, department, designation, branch and status.';
    }

    public function permissions(): array
    {
        return ['employees.view'];
    }

    public function params(): array
    {
        $statuses = [];
        foreach (EmployeeStatus::cases() as $status) {
            $statuses[$status->value] = Str::headline($status->value);
        }

        return [self::selectParam('status', 'Status', $statuses), self::branchParam()];
    }

    public function columns(array $params): array
    {
        return [
            self::col('employee_code', 'Code'),
            self::col('name', 'Name'),
            self::col('email', 'Email'),
            self::col('phone', 'Phone'),
            self::col('department', 'Department'),
            self::col('designation', 'Designation'),
            self::col('branch', 'Branch'),
            self::col('employment_type', 'Employment type'),
            self::col('status', 'Status'),
            self::col('hired_at', 'Hired', 'date'),
        ];
    }

    protected function query(ReportContext $context, array $params): Builder
    {
        return DB::table('employees as e')
            ->leftJoin('departments as d', 'd.id', '=', 'e.department_id')
            ->leftJoin('designations as ds', 'ds.id', '=', 'e.designation_id')
            ->leftJoin('branches as b', 'b.id', '=', 'e.branch_id')
            ->where('e.company_id', $context->companyId())
            ->whereNull('e.deleted_at')
            ->when($params['status'] ?? null, fn (Builder $q, string $status) => $q->where('e.status', $status))
            ->when($params['branch_id'] ?? null, fn (Builder $q, int $branchId) => $q->where('e.branch_id', $branchId))
            ->orderBy('e.first_name')->orderBy('e.last_name')->orderBy('e.id')
            ->select([
                'e.id', 'e.employee_code', 'e.first_name', 'e.last_name', 'e.email', 'e.phone',
                'd.name as department', 'ds.title as designation', 'b.name as branch',
                'e.employment_type', 'e.status', 'e.hired_at',
            ]);
    }

    protected function map(object $row): array
    {
        return [
            'employee_code' => $row->employee_code,
            'name' => trim($row->first_name.' '.$row->last_name),
            'email' => $row->email,
            'phone' => $row->phone,
            'department' => $row->department,
            'designation' => $row->designation,
            'branch' => $row->branch,
            'employment_type' => $row->employment_type,
            'status' => $row->status,
            'hired_at' => $row->hired_at ? Carbon::parse($row->hired_at)->toDateString() : null,
        ];
    }
}
