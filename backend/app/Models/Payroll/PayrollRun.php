<?php

namespace App\Models\Payroll;

use App\Enums\Payroll\PayrollRunStatus;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Finance\BankAccount;
use App\Models\Finance\JournalEntry;
use App\Models\User;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Payroll\PayrollRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A monthly payroll run. Lifecycle transitions go through the actions in
 * App\Actions\Payroll, each of which writes an explicit audit entry (the
 * model is intentionally not Auditable to keep amounts out of generic diffs).
 */
#[Fillable([
    'company_id', 'number', 'name', 'period_start', 'period_end', 'payment_date', 'currency', 'status',
    'branch_id', 'department_id', 'scope_key', 'total_gross', 'total_tax', 'total_deductions',
    'total_employer_contributions', 'total_net', 'employee_count', 'calculated_at', 'created_by',
    'approved_by', 'approved_at', 'finalized_by', 'finalized_at', 'paid_by', 'paid_at', 'bank_account_id',
    'cancelled_by', 'cancelled_at', 'journal_entry_id', 'payment_journal_entry_id', 'notes',
])]
class PayrollRun extends Model
{
    /** @use HasFactory<PayrollRunFactory> */
    use BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'payment_date' => 'date',
            'status' => PayrollRunStatus::class,
            'total_gross' => 'decimal:4',
            'total_tax' => 'decimal:4',
            'total_deductions' => 'decimal:4',
            'total_employer_contributions' => 'decimal:4',
            'total_net' => 'decimal:4',
            'employee_count' => 'integer',
            'calculated_at' => 'datetime',
            'approved_at' => 'datetime',
            'finalized_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public static function scopeKeyFor(string $start, string $end, ?int $branchId, ?int $departmentId): string
    {
        return implode('|', [$start, $end, $branchId ?? '*', $departmentId ?? '*']);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function paymentJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'payment_journal_entry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }
}
