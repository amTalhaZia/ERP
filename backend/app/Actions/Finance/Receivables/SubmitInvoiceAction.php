<?php

namespace App\Actions\Finance\Receivables;

use App\Enums\Finance\InvoiceStatus;
use App\Exceptions\Finance\FinanceException;
use App\Models\Finance\Invoice;
use App\Notifications\Finance\DocumentAwaitingApproval;
use App\Services\Finance\Receivables\ApproverDirectory;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Draft → PendingApproval, then notifies every holder of `invoices.approve`
 * (except the submitter).
 */
class SubmitInvoiceAction
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ApproverDirectory $approvers,
    ) {}

    public function handle(Invoice $invoice, ?int $userId = null): Invoice
    {
        DB::transaction(function () use ($invoice): void {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($locked->status !== InvoiceStatus::Draft) {
                throw FinanceException::conflict("Only draft invoices can be submitted; {$locked->number} is {$locked->status->value}.");
            }

            if (! $locked->lines()->exists()) {
                throw FinanceException::conflict('An invoice needs at least one line before it can be submitted.');
            }

            $locked->update(['status' => InvoiceStatus::PendingApproval]);
            $this->audit->log('invoices.submitted', $locked, ['status' => InvoiceStatus::Draft->value], ['status' => InvoiceStatus::PendingApproval->value]);
        });

        $invoice->refresh();

        Notification::send(
            $this->approvers->withPermission((int) $invoice->company_id, 'invoices.approve', $userId),
            new DocumentAwaitingApproval('invoice', $invoice->id, $invoice->number, (string) $invoice->total, $invoice->currency, (int) $invoice->company_id),
        );

        return $invoice;
    }
}
