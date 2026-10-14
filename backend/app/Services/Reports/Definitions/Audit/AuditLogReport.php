<?php

namespace App\Services\Reports\Definitions\Audit;

use App\Services\Reports\Definitions\QueryReportDefinition;
use App\Services\Reports\ReportContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The company's audit trail for a period (values/context omitted — they may
 * be large; the audit screen shows them per row).
 */
class AuditLogReport extends QueryReportDefinition
{
    public function key(): string
    {
        return 'audit.log';
    }

    public function module(): string
    {
        return 'audit';
    }

    public function name(): string
    {
        return 'Audit log';
    }

    public function description(): string
    {
        return 'Who did what and when: every audited action in the company for a period.';
    }

    public function permissions(): array
    {
        return ['audit.view'];
    }

    public function params(): array
    {
        return [self::dateParam('from', 'From'), self::dateParam('to', 'To')];
    }

    public function defaults(ReportContext $context): array
    {
        return ['from' => $context->monthStart(), 'to' => $context->today()];
    }

    public function columns(array $params): array
    {
        return [
            self::col('created_at', 'When', 'date'),
            self::col('actor_name', 'Actor'),
            self::col('action', 'Action'),
            self::col('entity_type', 'Entity'),
            self::col('entity_label', 'Record'),
            self::col('ip_address', 'IP address'),
        ];
    }

    protected function query(ReportContext $context, array $params): Builder
    {
        $tz = $context->company->timezone ?: 'UTC';

        return DB::table('audit_logs')
            ->where('company_id', $context->companyId())
            ->whereBetween('created_at', [
                Carbon::parse($params['from'], $tz)->startOfDay()->utc(),
                Carbon::parse($params['to'], $tz)->endOfDay()->utc(),
            ])
            ->orderBy('id')
            ->select(['id', 'created_at', 'actor_name', 'action', 'entity_type', 'entity_label', 'ip_address']);
    }

    protected function map(object $row): array
    {
        return [
            'created_at' => Carbon::parse($row->created_at, 'UTC')->toJSON(),
            'actor_name' => $row->actor_name,
            'action' => $row->action,
            'entity_type' => $row->entity_type,
            'entity_label' => $row->entity_label,
            'ip_address' => $row->ip_address,
        ];
    }
}
