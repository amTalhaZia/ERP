<?php

namespace App\Models\Finance;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One expense on a claim. `amount` is net of tax; tax_amount / line_total are
 * computed server-side. Receipt files live on the private `local` disk.
 */
#[Fillable([
    'company_id', 'expense_claim_id', 'line_no', 'expense_date', 'category', 'account_id', 'description',
    'amount', 'tax_rate_id', 'tax_rate', 'tax_amount', 'line_total', 'receipt_path', 'receipt_original_name',
])]
class ExpenseClaimLine extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'decimal:4',
            'tax_rate' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'line_total' => 'decimal:4',
        ];
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(ExpenseClaim::class, 'expense_claim_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }
}
