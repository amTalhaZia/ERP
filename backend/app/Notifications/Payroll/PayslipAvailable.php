<?php

namespace App\Notifications\Payroll;

use App\Models\Payroll\PayrollRun;
use App\Models\Payroll\Payslip;
use App\Notifications\ErpNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells an employee their payslip has been released. Deliberately carries NO
 * amounts (notifications and e-mails are not a secure channel for pay data);
 * the employee opens the payslip in the app, where access is re-checked.
 */
class PayslipAvailable extends ErpNotification
{
    private readonly int $payslipId;

    private readonly ?string $number;

    private readonly string $period;

    public function __construct(Payslip $payslip, PayrollRun $run)
    {
        parent::__construct();

        $this->companyId = (int) $run->company_id;
        $this->payslipId = (int) $payslip->id;
        $this->number = $payslip->number;
        $this->period = $run->period_start->toDateString().' – '.$run->period_end->toDateString();
    }

    public function type(): string
    {
        return 'payslip.available';
    }

    public function title(): string
    {
        return 'Your payslip is available';
    }

    public function body(): string
    {
        return "Your payslip {$this->number} for {$this->period} is now available.";
    }

    public function url(): string
    {
        return "/payroll/my-payslips/{$this->payslipId}";
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return ['payslip_id' => $this->payslipId];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->greeting('Hello '.(data_get($notifiable, 'name') ?: 'there').',')
            ->line($this->body())
            ->action('View payslip', $this->frontendUrl($this->url()));
    }
}
