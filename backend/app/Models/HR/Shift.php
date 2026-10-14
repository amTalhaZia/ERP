<?php

namespace App\Models\HR;

use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use Carbon\CarbonImmutable;
use Database\Factories\HR\ShiftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'name', 'start_time', 'end_time', 'break_minutes', 'days_of_week', 'is_default'])]
class Shift extends Model
{
    /** @use HasFactory<ShiftFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'break_minutes' => 'integer',
            'days_of_week' => 'array',
            'is_default' => 'boolean',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeShift::class);
    }

    /**
     * Whether the shift has a working day on the given date (ISO weekday 1-7).
     */
    public function worksOn(\DateTimeInterface $date): bool
    {
        return in_array((int) $date->format('N'), array_map('intval', $this->days_of_week ?? []), true);
    }

    /**
     * Shift start on a given calendar day, interpreted in the company timezone
     * and returned in UTC.
     */
    public function startsAt(string $day, string $timezone = 'UTC'): CarbonImmutable
    {
        return CarbonImmutable::parse($day.' '.$this->start_time, $timezone)->utc();
    }

    public function endsAt(string $day, string $timezone = 'UTC'): CarbonImmutable
    {
        $end = CarbonImmutable::parse($day.' '.$this->end_time, $timezone)->utc();
        $start = $this->startsAt($day, $timezone);

        return $end->lessThanOrEqualTo($start) ? $end->addDay() : $end;
    }
}
