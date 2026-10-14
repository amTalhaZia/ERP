<?php

namespace App\Models\Payroll;

use App\Enums\Payroll\TaxTablePeriod;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Payroll\TaxTableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Configurable progressive withholding table. The rules are entered by the
 * customer; jurisdiction-specific compliance is the customer's responsibility.
 */
#[Fillable(['company_id', 'name', 'effective_from', 'period', 'is_active', 'notes'])]
class TaxTable extends Model
{
    /** @use HasFactory<TaxTableFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'period' => TaxTablePeriod::class,
            'is_active' => 'boolean',
        ];
    }

    public function brackets(): HasMany
    {
        return $this->hasMany(TaxBracket::class)->orderBy('from_amount')->orderBy('id');
    }

    /**
     * The active table in force on $date (latest effective_from wins).
     */
    public static function effectiveOn(string $date): ?self
    {
        return static::query()
            ->with('brackets')
            ->where('is_active', true)
            ->where('effective_from', '<=', $date)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }
}
