<?php

namespace App\Models\HR;

use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\HR\EmployeeShiftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'employee_id', 'shift_id', 'effective_from', 'effective_to'])]
class EmployeeShift extends Model
{
    /** @use HasFactory<EmployeeShiftFactory> */
    use BelongsToCompany, HasFactory;

    protected $table = 'employee_shift';

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
