<?php

namespace App\Notifications\HR;

use App\Models\HR\Holiday;
use App\Notifications\ErpNotification;

/**
 * Tells company members about a newly added public holiday. In-app by
 * default; e-mail is opt-in (Settings → Notifications) so adding a holiday
 * never mass-mails the whole company.
 */
class HolidayAnnounced extends ErpNotification
{
    public const TYPE = 'holiday.announced';

    protected bool $emailByDefault = false;

    private readonly string $name;

    private readonly string $date;

    private readonly ?string $branch;

    public function __construct(Holiday $holiday)
    {
        parent::__construct();

        $this->companyId = (int) $holiday->company_id;
        $this->name = $holiday->name;
        $this->date = $holiday->date->format('l, j F Y');
        $this->branch = $holiday->relationLoaded('branch') ? $holiday->branch?->name : null;
    }

    public function type(): string
    {
        return self::TYPE;
    }

    public function title(): string
    {
        return "Holiday: {$this->name}";
    }

    public function body(): string
    {
        return "{$this->date} is a holiday ({$this->name})".($this->branch ? " for {$this->branch}." : '.');
    }

    public function url(): string
    {
        return '/hr/shifts';
    }
}
