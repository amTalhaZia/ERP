<?php

namespace App\Providers;

use App\Models\Payroll\EmployeeSalary;
use App\Models\Payroll\PayComponent;
use App\Models\Payroll\PayrollRun;
use App\Models\Payroll\Payslip;
use App\Models\Payroll\SalaryStructure;
use App\Models\Payroll\TaxTable;
use App\Policies\Payroll\EmployeeSalaryPolicy;
use App\Policies\Payroll\PayComponentPolicy;
use App\Policies\Payroll\PayrollRunPolicy;
use App\Policies\Payroll\PayslipPolicy;
use App\Policies\Payroll\SalaryStructurePolicy;
use App\Policies\Payroll\TaxTablePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Payroll module wiring. Policies live under App\Policies\Payroll and are
 * registered explicitly so the module is self-contained.
 */
class PayrollServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    private const POLICIES = [
        PayComponent::class => PayComponentPolicy::class,
        TaxTable::class => TaxTablePolicy::class,
        SalaryStructure::class => SalaryStructurePolicy::class,
        EmployeeSalary::class => EmployeeSalaryPolicy::class,
        PayrollRun::class => PayrollRunPolicy::class,
        Payslip::class => PayslipPolicy::class,
    ];

    public function boot(): void
    {
        foreach (self::POLICIES as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
