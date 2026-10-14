<?php

namespace App\Notifications\HR;

use App\Enums\HR\LeaveRequestStatus;
use App\Models\HR\LeaveRequest;
use Illuminate\Notifications\Notification;

/**
 * Database notification to the requester once a request is approved or rejected.
 */
class LeaveRequestDecided extends Notification
{
    public function __construct(private readonly LeaveRequest $request) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{type: string, title: string, body: string, url: string, company_id: int, status: string}
     */
    public function toDatabase(object $notifiable): array
    {
        $approved = $this->request->status === LeaveRequestStatus::Approved;
        $typeName = $this->request->relationLoaded('leaveType') ? $this->request->leaveType->name : 'Leave';
        $range = $this->request->start_date->toDateString().' – '.$this->request->end_date->toDateString();

        return [
            'type' => 'leave.decided',
            'title' => $approved ? 'Leave request approved' : 'Leave request rejected',
            'body' => $approved
                ? "Your {$typeName} request for {$range} ({$this->request->days} day(s)) was approved."
                : "Your {$typeName} request for {$range} was rejected.".($this->request->rejection_reason ? " Reason: {$this->request->rejection_reason}" : ''),
            'url' => "/hr/leave/{$this->request->id}",
            'company_id' => (int) $this->request->company_id,
            'status' => $this->request->status->value,
        ];
    }
}
