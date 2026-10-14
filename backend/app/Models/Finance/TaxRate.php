<?php

namespace App\Models\Finance;

use App\Enums\Finance\TaxRateType;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Finance\TaxRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'account_id', 'name', 'code', 'rate', 'type', 'is_compound', 'is_active'])]
class TaxRate extends Model
{
    /** @use HasFactory<TaxRateFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'type' => TaxRateType::class,
            'is_compound' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
