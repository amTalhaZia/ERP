<?php

namespace App\Notifications\HR;

use App\Models\HR\LeaveRequest;
use Illuminate\Notifications\Notification;

/**
 * Database notification to approvers (holders of leave.approve) on submission.
 */
class LeaveRequestSubmitted extends Notification
{
    public function __construct(
        private readonly LeaveRequest $request,
        private readonly string $employeeName,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{type: string, title: string, body: string, url: string, company_id: int}
     */
    public function toDatabase(object $notifiable): array
    {
        $range = $this->request->start_date->toDateString().' – '.$this->request->end_date->toDateString();

        return [
            'type' => 'leave.submitted',
            'title' => 'Leave request awaiting approval',
            'body' => "{$this->employeeName} requested {$this->request->days} day(s) of leave ({$range}).",
            'url' => "/hr/leave/{$this->request->id}",
            'company_id' => (int) $this->request->company_id,
        ];
    }
}
