<?php

namespace Database\Seeders\Demo;

use App\Actions\HR\ApproveLeaveRequestAction;
use App\Actions\HR\CreateEmployeeAction;
use App\Actions\HR\GenerateLeaveBalancesAction;
use App\Actions\HR\RecordAttendanceAction;
use App\Actions\HR\RejectLeaveRequestAction;
use App\Actions\HR\SubmitLeaveRequestAction;
use App\Enums\HR\ContractStatus;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\HR\Designation;
use App\Models\HR\Employee;
use App\Models\HR\EmploymentContract;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveType;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Database\Seeders\Demo\Concerns\SeedsDemoCompany;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * DEVELOPMENT ONLY. Designations, ~25 fictional employees (some linked to the
 * demo logins), contracts, 30 days of attendance, leave balances and a mix of
 * approved / pending / rejected leave requests — all through the HR actions.
 */
class HrDemoSeeder extends Seeder
{
    use SeedsDemoCompany;

    /** code => [title, level] */
    private const DESIGNATIONS = [
        'MD' => ['Managing Director', 1],
        'HEAD' => ['Head of Department', 2],
        'MGR' => ['Manager', 3],
        'LEAD' => ['Team Lead', 4],
        'SNR' => ['Senior Associate', 5],
        'ASSOC' => ['Associate', 6],
        'ANL' => ['Analyst', 6],
        'INT' => ['Intern', 8],
    ];

    /**
     * Role slots shared by both companies: [user local-part|null, department code, designation, branch slot, base salary (USD), employment type].
     * Branch slot 0 = head office, 1/2 = the first/second extra branch.
     */
    private const SLOTS = [
        ['hr', 'HR', 'HEAD', 0, 7500, 'full_time'],
        ['manager', 'OPS', 'HEAD', 0, 8000, 'full_time'],
        ['employee', 'OPS', 'ASSOC', 0, 3400, 'full_time'],
        ['accountant', 'FIN', 'MGR', 0, 6500, 'full_time'],
        ['sales', 'SAL', 'HEAD', 0, 8200, 'full_time'],
        ['inventory', 'OPS-WH', 'LEAD', 0, 5200, 'full_time'],
        ['procurement', 'PRC', 'SNR', 0, 4800, 'full_time'],
        [null, 'IT', 'MGR', 0, 6000, 'full_time'],
        [null, 'IT', 'ANL', 0, 4200, 'full_time'],
        [null, 'SAL', 'SNR', 1, 4800, 'full_time'],
        [null, 'SAL', 'ASSOC', 1, 3600, 'full_time'],
        [null, 'SAL', 'ASSOC', 2, 3500, 'full_time'],
        [null, 'SAL', 'ANL', 0, 3800, 'full_time'],
        [null, 'FIN-AP', 'SNR', 0, 4600, 'full_time'],
        [null, 'FIN', 'ANL', 0, 3900, 'full_time'],
        [null, 'OPS', 'LEAD', 2, 5200, 'full_time'],
        [null, 'OPS-WH', 'ASSOC', 2, 3100, 'full_time'],
        [null, 'OPS-WH', 'ASSOC', 0, 3000, 'full_time'],
        [null, 'OPS', 'ASSOC', 1, 3200, 'full_time'],
        [null, 'HR', 'ASSOC', 0, 3400, 'full_time'],
        [null, 'PRC', 'ASSOC', 0, 3500, 'full_time'],
        [null, 'IT', 'INT', 0, 1500, 'intern'],
        [null, 'OPS', 'ASSOC', 0, 2200, 'part_time'],
        [null, 'SAL', 'ASSOC', 0, 3300, 'contract'],
        [null, 'FIN', 'ANL', 1, 3700, 'full_time'],
    ];

    /** Fictional names, one per slot. */
    private const NAMES = [
        'northwind.test' => [
            'Hannah Reyes', 'Marcus Bell', 'Emily Carter', 'Aaron Brooks', 'Sofia Martinez', 'Ian Walsh', 'Priya Nair',
            'Daniel Kim', 'Grace Turner', 'Lucas Moreno', 'Olivia Grant', 'Noah Patel', 'Chloe Bennett', 'Ethan Hughes',
            'Ava Collins', 'Liam Foster', 'Mia Sanders', 'Jacob Price', 'Zoe Ramirez', 'Owen Fisher', 'Lily Howard',
            'Caleb Ward', 'Nora Jenkins', 'Isaac Perry', 'Ruby Coleman',
        ],
        'indus.test' => [
            'Hina Raza', 'Mohsin Baig', 'Ayesha Khan', 'Asad Qureshi', 'Sana Malik', 'Imran Shah', 'Bilal Ahmed',
            'Usman Tariq', 'Fatima Siddiqui', 'Hamza Javed', 'Zainab Hussain', 'Omar Farooq', 'Mariam Iqbal', 'Ali Haider',
            'Rabia Aslam', 'Faisal Mirza', 'Noor Fatima', 'Kamran Akhtar', 'Sadia Butt', 'Waqas Anwar', 'Amna Sheikh',
            'Danish Riaz', 'Huma Nadeem', 'Saad Latif', 'Iqra Yousaf',
        ],
    ];

    /** Department code => slot index of its head (manager_id for its members). */
    private const HEADS = ['HR' => 0, 'OPS' => 1, 'OPS-WH' => 5, 'FIN' => 3, 'FIN-AP' => 3, 'SAL' => 4, 'PRC' => 6, 'IT' => 7];

    public function run(Company $company): void
    {
        $this->bootDemo($company, 'hr');

        $designations = $this->designations();
        $employees = $this->employees($designations);
        $this->info(count($employees).' employees');

        $years = $this->leaveYears();
        foreach ($years as $year) {
            app(GenerateLeaveBalancesAction::class)->handle($year);
        }

        $this->leaveRequests($employees);
        $this->attendance($employees);
    }

    /** @return array<string, Designation> */
    private function designations(): array
    {
        $result = [];

        foreach (self::DESIGNATIONS as $code => [$title, $level]) {
            $result[$code] = Designation::query()->create(['title' => $title, 'code' => $code, 'level' => $level, 'is_active' => true]);
        }

        return $result;
    }

    /**
     * @param  array<string, Designation>  $designations
     * @return list<Employee>
     */
    private function employees(array $designations): array
    {
        $names = self::NAMES[$this->domain] ?? self::NAMES['northwind.test'];
        $departments = Department::query()->pluck('id', 'code');
        $headOffice = Branch::query()->where('is_head_office', true)->firstOrFail();
        $extraBranches = Branch::query()->where('is_head_office', false)->orderBy('id')->get()->values();
        $create = app(CreateEmployeeAction::class);
        $fiscalStart = $this->fiscalStart();

        /** @var list<Employee> $employees */
        $employees = [];

        $this->as('hr', function () use (&$employees, $names, $departments, $headOffice, $extraBranches, $create, $designations, $fiscalStart): void {
            foreach (self::SLOTS as $i => [$local, $dept, $designation, $branchSlot, $base, $type]) {
                [$first, $last] = explode(' ', $names[$i], 2);
                $branch = $branchSlot === 0 ? $headOffice : ($extraBranches[$branchSlot - 1] ?? $headOffice);
                $user = $local !== null ? $this->demoUser($local) : null;

                $hiredAt = $i === 21
                    ? $this->historyStart()->addMonth()->startOfMonth()          // the intern joined this year
                    : Carbon::instance(fake()->dateTimeBetween('2017-01-01', $fiscalStart->copy()->subMonths(2)->toDateString()))->startOfDay();

                $managerSlot = self::HEADS[$dept] ?? null;
                $managerId = ($managerSlot !== null && $managerSlot !== $i) ? ($employees[$managerSlot]->id ?? null) : null;
                if ($i === 5) { // warehouse lead reports to the operations head
                    $managerId = $employees[1]->id;
                }

                $employees[$i] = $create->handle([
                    'user_id' => $user?->id,
                    'first_name' => $first,
                    'last_name' => $last,
                    'email' => $user?->email ?? Str::lower(Str::ascii($first.'.'.str_replace(' ', '', $last))).'@'.$this->domain,
                    'phone' => fake()->numerify('+1 555 01## ####'),
                    'date_of_birth' => fake()->dateTimeBetween('-55 years', '-22 years')->format('Y-m-d'),
                    'gender' => fake()->randomElement(['male', 'female']),
                    'marital_status' => fake()->randomElement(['single', 'married']),
                    'address_line1' => fake()->buildingNumber().' Demo Avenue',
                    'city' => $branch->city ?? $this->company->city,
                    'country' => $this->company->country,
                    'emergency_contact_name' => fake()->firstName().' '.$last,
                    'emergency_contact_phone' => fake()->numerify('+1 555 02## ####'),
                    'emergency_contact_relationship' => fake()->randomElement(['Spouse', 'Parent', 'Sibling']),
                    'branch_id' => $branch->id,
                    'department_id' => $departments[$dept] ?? null,
                    'designation_id' => $designations[$designation]->id,
                    'manager_id' => $managerId,
                    'employment_type' => $type,
                    'status' => $hiredAt->greaterThan($fiscalStart) ? 'probation' : 'active',
                    'hired_at' => $hiredAt->toDateString(),
                    'probation_ends_at' => $hiredAt->copy()->addMonths(3)->toDateString(),
                    'bank_name' => 'Demo Bank (fictional)',
                    'bank_account_number' => fake()->numerify('00##########'),
                    'base_salary' => $this->amount($base),
                    'notes' => 'Demo employee — fictional data.',
                ]);

                EmploymentContract::query()->create([
                    'employee_id' => $employees[$i]->id,
                    'contract_type' => $type,
                    'start_date' => $hiredAt->toDateString(),
                    'end_date' => in_array($type, ['contract', 'intern'], true) ? $hiredAt->copy()->addYear()->max($this->today()->addMonths(3))->toDateString() : null,
                    'salary' => $this->amount($base),
                    'currency' => $this->company->currency,
                    'working_hours_per_week' => $type === 'part_time' ? '20.00' : '40.00',
                    'status' => ContractStatus::Active,
                    'notes' => 'Demo contract.',
                ]);
            }
        });

        foreach (self::HEADS as $code => $slot) {
            Department::query()->where('code', $code)->update(['manager_employee_id' => $employees[$slot]->id]);
        }

        return array_values($employees);
    }

    /** @return list<int> */
    private function leaveYears(): array
    {
        return array_values(array_unique([
            $this->historyStart()->year,
            $this->today()->year,
            $this->today()->addDays(60)->year,
        ]));
    }

    /**
     * @param  list<Employee>  $employees
     */
    private function leaveRequests(array $employees): void
    {
        $types = LeaveType::query()->pluck('id', 'code');
        $submit = app(SubmitLeaveRequestAction::class);
        $approve = app(ApproveLeaveRequestAction::class);
        $reject = app(RejectLeaveRequestAction::class);
        $start = $this->historyStart();
        $today = $this->today();

        // [slot, type, start, working days, outcome]
        $plan = [
            [2, 'ANNUAL', $start->copy()->addDays(30), 3, 'approve'],
            [10, 'SICK', $start->copy()->addDays(50), 2, 'approve'],
            [16, 'ANNUAL', $start->copy()->addDays(75), 5, 'approve'],
            [0, 'ANNUAL', $start->copy()->addDays(95), 2, 'approve'],
            [2, 'ANNUAL', $today->copy()->addDays(14), 2, 'pending'],
            [9, 'ANNUAL', $today->copy()->addDays(21), 4, 'pending'],
            [5, 'SICK', $today->copy()->addDays(9), 1, 'pending'],
            [19, 'ANNUAL', $today->copy()->addDays(30), 5, 'reject'],
        ];

        foreach ($plan as [$slot, $code, $from, $days, $outcome]) {
            $employee = $employees[$slot];
            $from = $this->weekday($from);
            $to = $from->copy();
            for ($n = 1; $n < $days; $n++) {
                $to = $this->weekday($to->addDay());
            }

            if ($employee->hired_at !== null && $from->lessThan($employee->hired_at)) {
                continue;
            }

            $submittedAt = $from->copy()->subDays(10)->setTime(10, 0)->min($this->realNow());

            $request = $this->at($submittedAt, fn () => $submit->handle($employee, [
                'leave_type_id' => $types[$code],
                'start_date' => $from->toDateString(),
                'end_date' => $to->toDateString(),
                'reason' => match ($code) {
                    'SICK' => 'Medical appointment and recovery.',
                    default => fake()->randomElement(['Family vacation.', 'Personal errands.', 'Visiting relatives.', 'Short break.']),
                },
            ]));

            // HR's own leave is approved by the department manager; everyone else's by HR.
            $approver = $employee->user_id === $this->demoUser('hr')->id ? 'manager' : 'hr';

            if ($outcome === 'approve') {
                $this->at($submittedAt->copy()->addDays(2), fn () => $this->as($approver, fn ($user) => $approve->handle($request, $user)));
            } elseif ($outcome === 'reject') {
                $this->as($approver, fn ($user) => $reject->handle($request, $user, 'Peak period for the team — please pick alternative dates.'));
            }
        }
    }

    /**
     * Weekday attendance for the last 30 days: mostly on time, a few late
     * arrivals and the odd absence; approved leave days are marked on_leave.
     *
     * @param  list<Employee>  $employees
     */
    private function attendance(array $employees): void
    {
        $timezone = $this->company->timezone ?: 'UTC';
        $record = app(RecordAttendanceAction::class);
        $from = $this->today()->subDays(30);
        $to = $this->today()->subDay();

        $onLeave = [];
        LeaveRequest::query()->where('status', 'approved')->get()->each(function (LeaveRequest $leave) use (&$onLeave): void {
            foreach (CarbonPeriod::create($leave->start_date, $leave->end_date) as $day) {
                $onLeave[$leave->employee_id][$day->toDateString()] = true;
            }
        });

        $this->as('hr', function () use ($employees, $timezone, $record, $from, $to, $onLeave): void {
            foreach (CarbonPeriod::create($from, $to) as $day) {
                if ($day->isWeekend()) {
                    continue;
                }

                $date = $day->toDateString();
                $rows = [];

                foreach ($employees as $employee) {
                    if ($employee->hired_at !== null && $employee->hired_at->toDateString() > $date) {
                        continue;
                    }

                    if (isset($onLeave[$employee->id][$date])) {
                        $rows[] = ['employee_id' => $employee->id, 'status' => 'on_leave'];

                        continue;
                    }

                    $roll = fake()->numberBetween(1, 100);

                    if ($roll <= 3) {
                        $rows[] = ['employee_id' => $employee->id, 'status' => 'absent', 'notes' => 'No show (demo).'];

                        continue;
                    }

                    $late = $roll <= 12;
                    $inMinutes = $late ? fake()->numberBetween(15, 70) : fake()->numberBetween(-20, 5);
                    $checkIn = CarbonImmutable::parse("{$date} 09:00", $timezone)->addMinutes($inMinutes);
                    $checkOut = CarbonImmutable::parse("{$date} 18:00", $timezone)->addMinutes(fake()->numberBetween(-15, 45));

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
