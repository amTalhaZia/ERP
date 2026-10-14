<?php

namespace App\Models\HR;

use App\Models\Branch;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\HR\HolidayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'branch_id', 'name', 'date', 'is_recurring'])]
class Holiday extends Model
{
    /** @use HasFactory<HolidayFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_recurring' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
