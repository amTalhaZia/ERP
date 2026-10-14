<?php

namespace App\Notifications\Procurement;

use App\Enums\Procurement\RequisitionStatus;
use App\Models\Procurement\PurchaseRequisition;
use Illuminate\Notifications\Notification;

/**
 * Sent to the requester when their requisition is approved or rejected.
 */
class RequisitionDecided extends Notification
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
        $approved = $this->requisition->status === RequisitionStatus::Approved;
        $approver = $this->requisition->approver?->name ?? 'An approver';

        return [
            'type' => $approved ? 'requisition.approved' : 'requisition.rejected',
            'title' => sprintf('Requisition %s was %s', $this->requisition->number, $approved ? 'approved' : 'rejected'),
            'body' => $approved
                ? "{$approver} approved your requisition {$this->requisition->number}."
                : "{$approver} rejected your requisition {$this->requisition->number}: {$this->requisition->rejection_reason}",
            'url' => "/procurement/requisitions/{$this->requisition->id}",
            'company_id' => $this->requisition->company_id,
            'requisition_id' => $this->requisition->id,
        ];
    }
}
