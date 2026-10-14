<?php

namespace App\Services\Reports;

use App\Models\User;
use App\Services\Reports\Definitions\Audit\AuditLogReport;
use App\Services\Reports\Definitions\Finance\ApAgingReport;
use App\Services\Reports\Definitions\Finance\ArAgingReport;
use App\Services\Reports\Definitions\Finance\BalanceSheetReport;
use App\Services\Reports\Definitions\Finance\ProfitAndLossReport;
use App\Services\Reports\Definitions\Finance\TrialBalanceReport;
use App\Services\Reports\Definitions\HR\AttendanceSummaryReport;
use App\Services\Reports\Definitions\HR\EmployeeDirectoryReport;
use App\Services\Reports\Definitions\HR\HeadcountReport;
use App\Services\Reports\Definitions\HR\LeaveSummaryReport;
use App\Services\Reports\Definitions\Inventory\InventoryValuationReport;
use App\Services\Reports\Definitions\Inventory\StockLevelsReport;
use App\Services\Reports\Definitions\Inventory\StockMovementsReport;
use App\Services\Reports\Definitions\Procurement\ProcurementSpendReport;
use App\Services\Reports\Definitions\Procurement\PurchaseOrderRegisterReport;
use App\Services\Reports\Definitions\Projects\TimeSummaryReport;
use App\Services\Reports\Definitions\ReportDefinition;
use App\Services\Reports\Definitions\Sales\SalesOrdersRegisterReport;
use App\Services\Reports\Definitions\Sales\SalesPerformanceReport;

/**
 * The report catalogue. Adding a report = one ReportDefinition class + one
 * line here.
 */
class ReportRegistry
{
    /** @var list<class-string<ReportDefinition>> */
    public const DEFINITIONS = [
        TrialBalanceReport::class,
        ProfitAndLossReport::class,
        BalanceSheetReport::class,
        ArAgingReport::class,
        ApAgingReport::class,
        SalesPerformanceReport::class,
        SalesOrdersRegisterReport::class,
        ProcurementSpendReport::class,
        PurchaseOrderRegisterReport::class,
        InventoryValuationReport::class,
        StockLevelsReport::class,
        StockMovementsReport::class,
        HeadcountReport::class,
        AttendanceSummaryReport::class,
        LeaveSummaryReport::class,
        EmployeeDirectoryReport::class,
        TimeSummaryReport::class,
        AuditLogReport::class,
    ];

    /** @var array<string, ReportDefinition>|null */
    private ?array $definitions = null;

    /**
     * @return array<string, ReportDefinition>
     */
    public function all(): array
    {
        if ($this->definitions === null) {
            $this->definitions = [];

            foreach (self::DEFINITIONS as $class) {
                $definition = app($class);
                $this->definitions[$definition->key()] = $definition;
            }
        }

        return $this->definitions;
    }

    public function find(string $key): ?ReportDefinition
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * @return list<ReportDefinition>
     */
    public function availableTo(User $user): array
    {
        return array_values(array_filter($this->all(), fn (ReportDefinition $definition) => $definition->authorizedFor($user)));
    }
}
