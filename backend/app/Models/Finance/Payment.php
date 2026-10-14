<?php

namespace App\Models\Finance;

use App\Enums\Finance\PaymentMethod;
use App\Enums\Finance\PaymentPartnerType;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Finance\PaymentType;
use App\Models\Branch;
use App\Models\User;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Finance\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Money in (Receipt, RCPT-...) or out (Payment, PAY-...) through a bank/cash
 * account, fully allocated to invoices, bills or expense claims of one partner.
 * Recorded payments are posted immediately; corrections are voids (reversal).
 */
#[Fillable([
    'company_id', 'branch_id', 'number', 'type', 'partner_type', 'partner_id', 'payment_date', 'method',
    'bank_account_id', 'amount', 'currency', 'reference', 'notes', 'status', 'journal_entry_id', 'created_by',
    'voided_at', 'void_reason',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'type' => PaymentType::class,
            'partner_type' => PaymentPartnerType::class,
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'payment_date' => 'date',
            'amount' => 'decimal:4',
            'voided_at' => 'datetime',
        ];
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function isPosted(): bool
    {
        return $this->status === PaymentStatus::Posted;
    }
}
