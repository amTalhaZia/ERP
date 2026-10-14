<?php

namespace App\Actions\Finance\Receivables;

use App\Enums\Finance\ExpenseClaimStatus;
use App\Exceptions\Finance\FinanceException;
use App\Models\Finance\ExpenseClaim;
use App\Notifications\Finance\DocumentAwaitingApproval;
use App\Services\Finance\Receivables\ApproverDirectory;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Draft → Submitted; notifies every holder of `expenses.approve` except the
 * submitter (who may not approve their own claim anyway).
 */
class SubmitExpenseClaimAction
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ApproverDirectory $approvers,
    ) {}

    public function handle(ExpenseClaim $claim): ExpenseClaim
    {
        DB::transaction(function () use ($claim): void {
            $locked = ExpenseClaim::query()->lockForUpdate()->findOrFail($claim->id);

            if ($locked->status !== ExpenseClaimStatus::Draft) {
                throw FinanceException::conflict("Only draft expense claims can be submitted; {$locked->number} is {$locked->status->value}.");
            }

            if (! $locked->lines()->exists() || Money::compare((string) $locked->total, '0') <= 0) {
                throw FinanceException::conflict('An expense claim needs at least one line with a positive amount.');
            }

            $locked->update(['status' => ExpenseClaimStatus::Submitted, 'submitted_at' => now()]);
            $this->audit->log('expense_claims.submitted', $locked, ['status' => ExpenseClaimStatus::Draft->value], ['status' => ExpenseClaimStatus::Submitted->value]);
        });

        $claim->refresh()->load('user:id,name');

        Notification::send(
            $this->approvers->withPermission((int) $claim->company_id, 'expenses.approve', (int) $claim->user_id),
            new DocumentAwaitingApproval('expense_claim', $claim->id, $claim->number, (string) $claim->total, $claim->currency, (int) $claim->company_id, $claim->user?->name),
        );

        return $claim;
    }
}
