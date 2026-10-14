<?php

namespace App\Actions\HR;

use App\Enums\HR\AttendanceSource;
use App\Enums\HR\AttendanceStatus;
use App\Models\HR\AttendanceRecord;
use App\Models\HR\Employee;
use App\Support\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Upserts the single attendance row for (employee, date), deriving worked and
 * late minutes from the shift whenever timestamps are present.
 */
class RecordAttendanceAction
{
    public function __construct(
        private readonly AttendanceCalculator $calculator,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{status?: string, check_in?: string|null, check_out?: string|null, source?: string, notes?: string|null}  $data
     */
    public function handle(Employee $employee, string $date, array $data, bool $audit = true): AttendanceRecord
    {
        return DB::transaction(function () use ($employee, $date, $data, $audit): AttendanceRecord {
            $record = AttendanceRecord::query()
                ->where('employee_id', $employee->id)
                ->whereDate('date', $date)
                ->lockForUpdate()
                ->first() ?? new AttendanceRecord(['employee_id' => $employee->id, 'date' => $date]);

            $wasNew = ! $record->exists;

            foreach (['status', 'check_in', 'check_out', 'source', 'notes'] as $key) {
                if (array_key_exists($key, $data)) {
                    $record->{$key} = $data[$key];
                }
            }

            $record->source ??= AttendanceSource::Manual;
            $record->status ??= AttendanceStatus::Present;

            $checkIn = $record->check_in ? CarbonImmutable::instance($record->check_in) : null;
            $checkOut = $record->check_out ? CarbonImmutable::instance($record->check_out) : null;
            $computed = $this->calculator->compute($employee, $date, $checkIn, $checkOut);

            $record->worked_minutes = $computed['worked_minutes'];
            $record->late_minutes = $computed['late_minutes'];
            $record->save();

            if ($audit) {
                $this->audit->log(
                    $wasNew ? 'attendance.recorded' : 'attendance.updated',
                    $record,
                    null,
                    ['employee_id' => $employee->id, 'date' => $date, 'status' => $record->status->value],
                    entityLabel: $employee->fullName().' '.$date,
                );
            }

            return $record;
        });
    }

    /**
     * Upsert many rows for one date. Returns the number of rows written.
     *
     * @param  array<int, array{employee_id: int, status: string, check_in?: string|null, check_out?: string|null, notes?: string|null}>  $rows
     */
    public function bulk(string $date, array $rows, string $source = 'manual'): int
    {
        return DB::transaction(function () use ($date, $rows, $source): int {
            $employees = Employee::query()->whereIn('id', array_column($rows, 'employee_id'))->get()->keyBy('id');
            $written = 0;

            foreach ($rows as $row) {
                $employee = $employees->get((int) $row['employee_id']);

                if ($employee === null) {
                    continue;
                }

                $this->handle($employee, $date, [
                    'status' => $row['status'],
                    'check_in' => $row['check_in'] ?? null,
                    'check_out' => $row['check_out'] ?? null,
                    'notes' => $row['notes'] ?? null,
                    'source' => $source,
                ], audit: false);
                $written++;
            }

            $this->audit->log('attendance.bulk_recorded', null, null, null, ['date' => $date, 'rows' => $written, 'source' => $source]);

            return $written;
        });
    }
}
