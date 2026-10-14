<?php

namespace App\Services\Reports\Definitions;

use App\Services\Reports\ReportContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Row-level register backed by one ordered query: counts, pages and exports
 * run in the database (COUNT, LIMIT/OFFSET, chunked lazy iteration) rather
 * than in memory. `query()` MUST constrain on the context's company id.
 */
abstract class QueryReportDefinition extends ReportDefinition
{
    /**
     * Ordered, tenant-constrained query.
     *
     * @param  array<string, mixed>  $params
     */
    abstract protected function query(ReportContext $context, array $params): Builder;

    /**
     * @return array<string, mixed>
     */
    abstract protected function map(object $row): array;

    protected function compute(ReportContext $context, array $params): array
    {
        return ['rows' => $this->query($context, $params)->get()->map(fn (object $row) => $this->map($row))->all()];
    }

    public function count(ReportContext $context, array $params): int
    {
        return (int) DB::query()->fromSub($this->query($context, $params)->reorder(), 'report_rows')->count();
    }

    public function slice(ReportContext $context, array $params, int $offset, int $limit): array
    {
        return $this->query($context, $params)->offset($offset)->limit($limit)->get()
            ->map(fn (object $row) => $this->map($row))->values()->all();
    }

    public function cursor(ReportContext $context, array $params): iterable
    {
        foreach ($this->query($context, $params)->lazy(1000) as $row) {
            yield $this->map($row);
        }
    }

    public function totals(ReportContext $context, array $params): ?array
    {
        return null;
    }
}
