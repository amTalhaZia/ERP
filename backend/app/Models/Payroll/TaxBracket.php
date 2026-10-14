<?php

namespace App\Models\Payroll;

use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Payroll\TaxBracketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'tax_table_id', 'from_amount', 'to_amount', 'rate', 'fixed_amount', 'sort_order'])]
class TaxBracket extends Model
{
    /** @use HasFactory<TaxBracketFactory> */
    use BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'from_amount' => 'decimal:4',
            'to_amount' => 'decimal:4',
            'rate' => 'decimal:4',
            'fixed_amount' => 'decimal:4',
        ];
    }

    public function taxTable(): BelongsTo
    {
        return $this->belongsTo(TaxTable::class);
    }
}
