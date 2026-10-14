<?php

namespace App\Notifications\Finance;

use Illuminate\Notifications\Notification;

/**
 * Sent to every holder of the matching approve permission when an invoice,
 * bill or expense claim is submitted. Database channel only; the approval
 * inbox reads `type`, `title`, `body`, `url` and `company_id`.
 */
class DocumentAwaitingApproval extends Notification
{
    private const LABELS = [
        'invoice' => ['Invoice', '/finance/invoices/'],
        'bill' => ['Supplier bill', '/finance/bills/'],
        'expense_claim' => ['Expense claim', '/finance/expense-claims/'],
    ];

    public function __construct(
        private readonly string $documentType,
        private readonly int $documentId,
        private readonly string $number,
        private readonly string $total,
        private readonly string $currency,
        private readonly int $companyId,
        private readonly ?string $submittedBy = null,
    ) {}

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
        [$label, $path] = self::LABELS[$this->documentType];
        $amount = number_format((float) $this->total, 2, '.', ',');

        return [
            'type' => "{$this->documentType}.submitted",
            'title' => "{$label} {$this->number} awaits your approval",
            'body' => trim(($this->submittedBy ? "{$this->submittedBy} submitted " : 'Submitted: ')."{$label} {$this->number} for {$this->currency} {$amount}."),
            'url' => $path.$this->documentId,
            'company_id' => $this->companyId,
            'document_type' => $this->documentType,
            'document_id' => $this->documentId,
        ];
    }
}
