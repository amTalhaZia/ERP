<?php

namespace App\Notifications\Finance;

use App\Enums\Finance\ExpenseClaimStatus;
use App\Models\Finance\ExpenseClaim;
use Illuminate\Notifications\Notification;

/**
 * Tells the submitter that their expense claim was approved or rejected.
 */
class ExpenseClaimDecided extends Notification
{
    public function __construct(private readonly ExpenseClaim $claim) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $approved = $this->claim->status === ExpenseClaimStatus::Approved;
        $amount = number_format((float) (string) $this->claim->total, 2, '.', ',');

        return [
            'type' => $approved ? 'expense_claim.approved' : 'expense_claim.rejected',
            'title' => "Expense claim {$this->claim->number} ".($approved ? 'approved' : 'rejected'),
            'body' => $approved
                ? "Your claim for {$this->claim->currency} {$amount} was approved and is queued for reimbursement."
                : "Your claim for {$this->claim->currency} {$amount} was rejected: {$this->claim->rejection_reason}",
            'url' => "/finance/expense-claims/{$this->claim->id}",
            'company_id' => (int) $this->claim->company_id,
            'expense_claim_id' => $this->claim->id,
        ];
    }
}
