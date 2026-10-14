<?php

namespace App\Actions\Finance;

use App\Enums\Finance\PeriodStatus;
use App\Exceptions\Finance\FinanceException;
use App\Models\Finance\FinancialPeriod;
use App\Models\Finance\FinancialYear;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Closes a period. Periods close in order: every earlier period of the company
 * must already be closed. When the last period of a year closes, the year
 * closes too.
 */
class ClosePeriodAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(FinancialPeriod $period, ?int $userId): FinancialPeriod
    {
        return DB::transaction(function () use ($period, $userId): FinancialPeriod {
            $period = FinancialPeriod::forCompany($period->company_id)->whereKey($period->id)->lockForUpdate()->firstOrFail();

            if (! $period->isOpen()) {
                throw FinanceException::conflict("Period {$period->name} is already closed.");
            }

            $earlierOpen = FinancialPeriod::forCompany($period->company_id)
                ->open()
                ->where('start_date', '<', $period->start_date->toDateString())
                ->exists();

            if ($earlierOpen) {
                throw FinanceException::conflict('Close earlier periods first.', 'finance.earlier_period_open');
            }

            $period->fill([
                'status' => PeriodStatus::Closed,
                'closed_by' => $userId,
                'closed_at' => now(),
            ])->save();

            $this->syncYearStatus($period->financial_year_id, $period->company_id);

            $this->audit->log('periods.closed', $period, companyId: $period->company_id);

            return $period;
        });
    }

    public static function syncYearStatus(int $yearId, int $companyId): void
    {
        $anyOpen = FinancialPeriod::forCompany($companyId)->where('financial_year_id', $yearId)->open()->exists();

        FinancialYear::forCompany($companyId)->whereKey($yearId)
            ->update(['status' => $anyOpen ? PeriodStatus::Open->value : PeriodStatus::Closed->value]);
    }
}
