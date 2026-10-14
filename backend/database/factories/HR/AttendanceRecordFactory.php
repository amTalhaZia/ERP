<?php

namespace Database\Factories\HR;

use App\Enums\HR\AttendanceSource;
use App\Enums\HR\AttendanceStatus;
use App\Models\HR\AttendanceRecord;
use App\Models\HR\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    protected $model = AttendanceRecord::class;

    public function definition(): array
    {
        $date = fake()->unique()->dateTimeBetween('-60 days', '-1 day')->format('Y-m-d');

        return [
            'employee_id' => Employee::factory(),
            'company_id' => fn (array $attrs) => Employee::withoutTenancy()->find($attrs['employee_id'])->company_id,
            'date' => $date,
            'check_in' => $date.' 09:00:00',
            'check_out' => $date.' 18:00:00',
            'status' => AttendanceStatus::Present,
            'worked_minutes' => 480,
            'late_minutes' => 0,
            'source' => AttendanceSource::Manual,
        ];
    }

    public function absent(): static
    {
        return $this->state(['status' => AttendanceStatus::Absent, 'check_in' => null, 'check_out' => null, 'worked_minutes' => 0]);
    }
}
