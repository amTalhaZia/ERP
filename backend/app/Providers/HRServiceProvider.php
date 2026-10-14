<?php

namespace App\Providers;

use App\Models\HR\AttendanceRecord;
use App\Models\HR\Designation;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeDocument;
use App\Models\HR\EmploymentContract;
use App\Models\HR\Holiday;
use App\Models\HR\LeaveBalance;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveType;
use App\Models\HR\PerformanceReview;
use App\Models\HR\Shift;
use App\Policies\HR\AttendanceRecordPolicy;
use App\Policies\HR\DesignationPolicy;
use App\Policies\HR\EmployeeDocumentPolicy;
use App\Policies\HR\EmployeePolicy;
use App\Policies\HR\EmploymentContractPolicy;
use App\Policies\HR\HolidayPolicy;
use App\Policies\HR\LeaveBalancePolicy;
use App\Policies\HR\LeaveRequestPolicy;
use App\Policies\HR\LeaveTypePolicy;
use App\Policies\HR\PerformanceReviewPolicy;
use App\Policies\HR\ShiftPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * HR & Employee Management module wiring. Policies live under App\Policies\HR
 * and are registered explicitly so the module is self-contained.
 */
class HRServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    private const POLICIES = [
        Designation::class => DesignationPolicy::class,
        Employee::class => EmployeePolicy::class,
        EmploymentContract::class => EmploymentContractPolicy::class,
        Shift::class => ShiftPolicy::class,
        Holiday::class => HolidayPolicy::class,
        AttendanceRecord::class => AttendanceRecordPolicy::class,
        LeaveType::class => LeaveTypePolicy::class,
        LeaveBalance::class => LeaveBalancePolicy::class,
        LeaveRequest::class => LeaveRequestPolicy::class,
        PerformanceReview::class => PerformanceReviewPolicy::class,
        EmployeeDocument::class => EmployeeDocumentPolicy::class,
    ];

    public function boot(): void
    {
        foreach (self::POLICIES as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
