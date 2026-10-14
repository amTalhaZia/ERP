<?php

namespace App\Actions\Finance;

use App\Enums\Finance\PeriodStatus;
use App\Exceptions\Finance\FinanceException;
use App\Models\Finance\FinancialPeriod;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Reopens a closed period (sensitive: explicitly audited as `periods.reopened`).
 * Later periods must still be open so the closed range stays contiguous.
 */
class ReopenPeriodAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(FinancialPeriod $period, ?string $reason = null): FinancialPeriod
    {
        return DB::transaction(function () use ($period, $reason): FinancialPeriod {
            $period = FinancialPeriod::forCompany($period->company_id)->whereKey($period->id)->lockForUpdate()->firstOrFail();

            if ($period->isOpen()) {
                throw FinanceException::conflict("Period {$period->name} is already open.");
            }

            $laterClosed = FinancialPeriod::forCompany($period->company_id)
                ->where('status', PeriodStatus::Closed->value)
                ->where('start_date', '>', $period->start_date->toDateString())
                ->exists();

            if ($laterClosed) {
                throw FinanceException::conflict('Reopen later periods first.', 'finance.later_period_closed');
            }

            $closedAt = $period->closed_at;
            $period->fill(['status' => PeriodStatus::Open, 'closed_by' => null, 'closed_at' => null])->save();

            ClosePeriodAction::syncYearStatus($period->financial_year_id, $period->company_id);

            $this->audit->log('periods.reopened', $period, companyId: $period->company_id, context: [
                'reason' => $reason,
                'previously_closed_at' => $closedAt?->toJSON(),
            ]);

            return $period;
        });
    }
}
