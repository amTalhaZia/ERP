<?php

namespace App\Models\Finance;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Portion of a payment settling one document. `allocatable_type` is a short
 * slug (invoice | bill | expense_claim) mapped by TYPES. Rows are kept when the
 * payment is voided (history); the payment status tells whether they count.
 */
#[Fillable(['company_id', 'payment_id', 'allocatable_type', 'allocatable_id', 'amount'])]
class PaymentAllocation extends Model
{
    use BelongsToCompany;

    /** @var array<string, class-string<Model>> */
    public const TYPES = [
        'invoice' => Invoice::class,
        'bill' => Bill::class,
        'expense_claim' => ExpenseClaim::class,
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
