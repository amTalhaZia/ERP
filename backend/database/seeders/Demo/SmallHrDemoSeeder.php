<?php

namespace Database\Seeders\Demo;

use App\Actions\HR\ApproveLeaveRequestAction;
use App\Actions\HR\RecordAttendanceAction;
use App\Actions\HR\RejectLeaveRequestAction;
use App\Actions\HR\SubmitLeaveRequestAction;
use App\Models\Company;
use App\Models\HR\Employee;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveType;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Database\Seeders\Demo\Concerns\SeedsDemoCompany;
use Illuminate\Database\Seeder;

/**
 * DEVELOPMENT / DEMO ONLY. HR activity for the small demo: six leave requests
 * (approved, pending and rejected) and two weeks of weekday attendance.
 * HR approves everyone's leave; HR's own leave is approved by owner@.
 */
class SmallHrDemoSeeder extends Seeder
{
    use SeedsDemoCompany;

    public function run(Company $company): void
    {
        $this->bootDemo($company, 'small-hr');

        $employees = Employee::query()->orderBy('id')->get()->keyBy(fn (Employee $e) => $e->first_name);
        $this->leaveRequests($employees->all());
        $this->attendance($employees->values()->all());

        $this->info(LeaveRequest::query()->count().' leave requests');
    }

    /** @param array<string, Employee> $people keyed by first name */
    private function leaveRequests(array $people): void
    {
        $types = LeaveType::query()->pluck('id', 'code');
        $submit = app(SubmitLeaveRequestAction::class);
        $approve = app(ApproveLeaveRequestAction::class);
        $reject = app(RejectLeaveRequestAction::class);
        $today = $this->today();

        // [first name, leave type, start, working days, outcome, reason]
        $plan = [
            ['Emily', 'ANNUAL', $today->copy()->subDays(9), 2, 'approve', 'Family vacation.'],
            ['Ruby', 'SICK', $today->copy()->subDays(5), 1, 'approve', 'Medical appointment and recovery.'],
            ['Hannah', 'ANNUAL', $today->copy()->subDays(30), 3, 'approve', 'Short break.'],
            ['Ian', 'ANNUAL', $today->copy()->addDays(10), 3, 'pending', 'Visiting relatives.'],
            ['Emily', 'SICK', $today->copy()->addDays(4), 1, 'pending', 'Dental surgery.'],
            ['Aaron', 'ANNUAL', $today->copy()->addDays(6), 5, 'reject', 'Holiday trip.'],
        ];

        foreach ($plan as [$name, $code, $from, $days, $outcome, $reason]) {
            $employee = $people[$name] ?? null;
            if ($employee === null || ! isset($types[$code])) {
                continue;
            }

            $from = $this->weekday($from);
            $to = $from->copy();
            for ($n = 1; $n < $days; $n++) {
                $to = $this->weekday($to->addDay());
            }

            $submittedAt = $from->copy()->subDays(7)->setTime(10, 0)->min($this->realNow());
            $request = $this->at($submittedAt, fn () => $submit->handle($employee, [
                'leave_type_id' => $types[$code],
                'start_date' => $from->toDateString(),
                'end_date' => $to->toDateString(),
                'reason' => $reason,
            ]));

            $approver = $employee->user_id === $this->demoUser('hr')->id ? 'owner' : 'hr';

            if ($outcome === 'approve') {
                $this->at($submittedAt->copy()->addDay(), fn () => $this->as($approver, fn ($user) => $approve->handle($request, $user)));
            } elseif ($outcome === 'reject') {
                $this->as($approver, fn ($user) => $reject->handle($request, $user, 'Quarter-end close — please pick other dates.'));
            }
        }
    }

    /**
     * Two weeks of weekday attendance: mostly on time, a few late arrivals,
     * one absence, and approved leave days marked on_leave.
     *
     * @param  list<Employee>  $employees
     */
    private function attendance(array $employees): void
    {
        $timezone = $this->company->timezone ?: 'UTC';
        $record = app(RecordAttendanceAction::class);

        $onLeave = [];
        LeaveRequest::query()->where('status', 'approved')->get()->each(function (LeaveRequest $leave) use (&$onLeave): void {
            foreach (CarbonPeriod::create($leave->start_date, $leave->end_date) as $day) {
                $onLeave[$leave->employee_id][$day->toDateString()] = true;
            }
        });

        $this->as('hr', function () use ($employees, $timezone, $record, $onLeave): void {
            foreach (CarbonPeriod::create($this->today()->subDays(14), $this->today()->subDay()) as $day) {
                if ($day->isWeekend()) {
                    continue;
                }

                $date = $day->toDateString();
                $rows = [];

                foreach ($employees as $i => $employee) {
                    if (isset($onLeave[$employee->id][$date])) {
                        $rows[] = ['employee_id' => $employee->id, 'status' => 'on_leave'];

                        continue;
                    }

                    $roll = fake()->numberBetween(1, 100);
                    if ($roll <= 3) {
                        $rows[] = ['employee_id' => $employee->id, 'status' => 'absent', 'notes' => 'No show (demo).'];

                        continue;
                    }

                    $late = $roll <= 15;
                    $checkIn = CarbonImmutable::parse("{$date} 09:00", $timezone)->addMinutes($late ? fake()->numberBetween(15, 50) : fake()->numberBetween(-20, 5));
                    $checkOut = CarbonImmutable::parse("{$date} 18:00", $timezone)->addMinutes(fake()->numberBetween(-10, 40));

                    $rows[] = [
                        'employee_id' => $employee->id,
                        'status' => $late ? 'late' : 'present',
                        'check_in' => $checkIn->utc()->format('Y-m-d H:i:s'),
                        'check_out' => $checkOut->utc()->format('Y-m-d H:i:s'),
                    ];
                }

                $record->bulk($date, $rows, 'manual');
            }
        });
    }
}
