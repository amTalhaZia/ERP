<?php

namespace App\Notifications\Procurement;

use App\Models\Procurement\PurchaseRequisition;
use Illuminate\Notifications\Notification;

/**
 * Sent to every user holding `requisitions.approve` in the company when a
 * requisition is submitted. Database channel only; the approval inbox reads
 * `type`, `title`, `body`, `url` and `company_id`.
 */
class RequisitionSubmitted extends Notification
{
    public function __construct(private readonly PurchaseRequisition $requisition) {}

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
        $requester = $this->requisition->requester?->name ?? 'A colleague';

        return [
            'type' => 'requisition.submitted',
            'title' => "Requisition {$this->requisition->number} awaits your approval",
            'body' => sprintf(
                '%s submitted %s (%s priority, estimated %s).',
                $requester,
                $this->requisition->number,
                $this->requisition->priority->value,
                $this->requisition->estimated_total,
            ),
            'url' => "/procurement/requisitions/{$this->requisition->id}",
            'company_id' => $this->requisition->company_id,
            'requisition_id' => $this->requisition->id,
        ];
    }
}
