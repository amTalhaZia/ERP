<?php

namespace App\Models\HR;

use App\Enums\HR\AttendanceSource;
use App\Enums\HR\AttendanceStatus;
use App\Support\Tenancy\BelongsToCompany;
use App\Support\Tenancy\RestrictsToAllowedBranches;
use Database\Factories\HR\AttendanceRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Not Auditable on purpose: bulk/biometric imports would flood the audit
 * trail. Controllers write one explicit audit entry per operation instead.
 */
#[Fillable([
    'company_id', 'employee_id', 'date', 'check_in', 'check_out', 'status', 'worked_minutes', 'late_minutes',
    'source', 'notes',
])]
class AttendanceRecord extends Model
{
    /** @use HasFactory<AttendanceRecordFactory> */
    use BelongsToCompany, HasFactory, RestrictsToAllowedBranches;

    /** @var array<int, string> branch restriction derived via these parent relations */
    protected array $branchRelations = ['employee'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'check_in' => 'datetime',
            'check_out' => 'datetime',
            'status' => AttendanceStatus::class,
            'worked_minutes' => 'integer',
            'late_minutes' => 'integer',
            'source' => AttendanceSource::class,
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
