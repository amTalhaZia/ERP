<?php

namespace App\Models\HR;

use App\Enums\HR\EmployeeStatus;
use App\Enums\HR\EmploymentType;
use App\Enums\HR\Gender;
use App\Enums\HR\MaritalStatus;
use App\Models\Branch;
use App\Models\Department;
use App\Models\User;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use App\Support\Tenancy\RestrictsToAllowedBranches;
use Database\Factories\HR\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'company_id', 'employee_code', 'user_id', 'first_name', 'last_name', 'email', 'phone', 'date_of_birth',
    'gender', 'national_id', 'marital_status', 'address_line1', 'address_line2', 'city', 'state', 'postal_code',
    'country', 'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship',
    'branch_id', 'department_id', 'designation_id', 'manager_id', 'employment_type', 'status', 'hired_at',
    'probation_ends_at', 'terminated_at', 'termination_reason', 'photo_path', 'bank_name',
    'bank_account_number', 'bank_iban', 'tax_identifier', 'base_salary', 'currency', 'notes',
])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use Auditable, BelongsToCompany, HasFactory, RestrictsToAllowedBranches, SoftDeletes;

    /** Branch restriction: direct `branch_id` column. */
    protected string $branchColumn = 'branch_id';

    /** @var array<int, string> encrypted / bulky attributes never written to the audit trail */
    protected array $auditExclude = ['national_id', 'bank_account_number', 'bank_iban', 'tax_identifier', 'photo_path', 'notes'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'gender' => Gender::class,
            'marital_status' => MaritalStatus::class,
            'national_id' => 'encrypted',
            'bank_account_number' => 'encrypted',
            'bank_iban' => 'encrypted',
            'tax_identifier' => 'encrypted',
            'employment_type' => EmploymentType::class,
            'status' => EmployeeStatus::class,
            'hired_at' => 'date',
            'probation_ends_at' => 'date',
            'terminated_at' => 'date',
            'base_salary' => 'decimal:4',
        ];
    }

    // -- Relationships -----------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(EmploymentContract::class);
    }

    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(EmployeeShift::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function performanceReviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class);
    }

    // -- Scopes --------------------------------------------------------------

    /**
     * Employees still on the books (anything but terminated).
     *
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    public function scopeEmployed(Builder $query): Builder
    {
        return $query->where('status', '!=', EmployeeStatus::Terminated->value);
    }

    // -- Helpers -------------------------------------------------------------

    /**
     * The current tenant's employee record linked to the given login account.
     */
    public static function forUser(User|int $user): ?self
    {
        $id = $user instanceof User ? $user->id : $user;

        return static::query()->where('user_id', $id)->first();
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    public function isTerminated(): bool
    {
        return $this->status === EmployeeStatus::Terminated;
    }

    /**
     * The shift in effect for this employee on a given date: an explicit
     * assignment covering the date, otherwise the company default shift.
     */
    public function shiftOn(\DateTimeInterface|string $date): ?Shift
    {
        $day = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : $date;

        $assignment = EmployeeShift::query()
            ->with('shift')
            ->where('employee_id', $this->id)
            ->where('effective_from', '<=', $day)
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $day))
            ->orderByDesc('effective_from')
            ->first();

        return $assignment?->shift ?? Shift::query()->where('is_default', true)->first();
    }
}
