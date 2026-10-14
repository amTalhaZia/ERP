<?php

namespace App\Actions\Finance\Receivables;

use App\Enums\Finance\BillStatus;
use App\Exceptions\Finance\FinanceException;
use App\Models\Finance\Bill;
use App\Notifications\Finance\DocumentAwaitingApproval;
use App\Services\Finance\Receivables\ApproverDirectory;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Draft → PendingApproval, then notifies every holder of `bills.approve`
 * (except the submitter).
 */
class SubmitBillAction
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ApproverDirectory $approvers,
    ) {}

    public function handle(Bill $bill, ?int $userId = null): Bill
    {
        DB::transaction(function () use ($bill): void {
            $locked = Bill::query()->lockForUpdate()->findOrFail($bill->id);

            if ($locked->status !== BillStatus::Draft) {
                throw FinanceException::conflict("Only draft bills can be submitted; {$locked->number} is {$locked->status->value}.");
            }

            if (! $locked->lines()->exists()) {
                throw FinanceException::conflict('A bill needs at least one line before it can be submitted.');
            }

            $locked->update(['status' => BillStatus::PendingApproval]);
            $this->audit->log('bills.submitted', $locked, ['status' => BillStatus::Draft->value], ['status' => BillStatus::PendingApproval->value]);
        });

        $bill->refresh();

        Notification::send(
            $this->approvers->withPermission((int) $bill->company_id, 'bills.approve', $userId),
            new DocumentAwaitingApproval('bill', $bill->id, $bill->number, (string) $bill->total, $bill->currency, (int) $bill->company_id),
        );

        return $bill;
    }
}
