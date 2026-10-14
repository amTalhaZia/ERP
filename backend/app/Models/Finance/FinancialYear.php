<?php

namespace App\Models\Finance;

use App\Enums\Finance\PeriodStatus;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Finance\FinancialYearFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'name', 'start_date', 'end_date', 'status'])]
class FinancialYear extends Model
{
    /** @use HasFactory<FinancialYearFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => PeriodStatus::class,
        ];
    }

    public function periods(): HasMany
    {
        return $this->hasMany(FinancialPeriod::class)->orderBy('start_date');
    }
}
