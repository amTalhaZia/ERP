<?php

namespace App\Models\Payroll;

use App\Enums\Payroll\PayrollRunStatus;
use App\Models\HR\Employee;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Payroll\PayslipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One employee's pay for a run. Employee details are snapshotted so later HR
 * edits never rewrite a released payslip. `status` mirrors the run.
 */
#[Fillable([
    'company_id', 'payroll_run_id', 'employee_id', 'employee_salary_id', 'number', 'status', 'employee_code',
    'employee_name', 'department_id', 'department_name', 'designation_title', 'branch_id', 'bank_name',
    'bank_account_last4', 'currency', 'base_salary', 'working_days', 'unpaid_days', 'paid_days', 'earned_base',
    'gross', 'taxable', 'tax', 'total_deductions', 'employer_contributions', 'net', 'warnings',
])]
class Payslip extends Model
{
    /** @use HasFactory<PayslipFactory> */
    use BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'status' => PayrollRunStatus::class,
            'base_salary' => 'decimal:4',
            'working_days' => 'decimal:1',
            'unpaid_days' => 'decimal:1',
            'paid_days' => 'decimal:1',
            'earned_base' => 'decimal:4',
            'gross' => 'decimal:4',
            'taxable' => 'decimal:4',
            'tax' => 'decimal:4',
            'total_deductions' => 'decimal:4',
            'employer_contributions' => 'decimal:4',
            'net' => 'decimal:4',
            'warnings' => 'array',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayslipLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function isReleased(): bool
    {
        return $this->status->isReleased();
    }
}
