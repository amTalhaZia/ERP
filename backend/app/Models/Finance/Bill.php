<?php

namespace App\Models\Finance;

use App\Enums\Finance\BillStatus;
use App\Enums\Finance\PaymentPartnerType;
use App\Models\Branch;
use App\Models\User;
use App\Services\Finance\Receivables\Settleable;
use App\Support\Audit\Auditable;
use App\Support\Money\Money;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Finance\BillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Supplier bill (AP sub-ledger). Approval posts it to the GL; from then on
 * it is immutable except for payment settlement fields and voiding.
 * supplier_id / purchase_order_id reference the Procurement module without FKs.
 */
#[Fillable([
    'company_id', 'branch_id', 'number', 'supplier_id', 'purchase_order_id', 'bill_date', 'due_date', 'currency',
    'status', 'subtotal', 'discount_total', 'tax_total', 'total', 'amount_paid', 'balance_due', 'supplier_reference', 'notes',
    'journal_entry_id', 'created_by', 'approved_by', 'approved_at', 'voided_at', 'void_reason',
])]
class Bill extends Model implements Settleable
{
    /** @use HasFactory<BillFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'bill_date' => 'date',
            'due_date' => 'date',
            'status' => BillStatus::class,
            'subtotal' => 'decimal:4',
            'discount_total' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'total' => 'decimal:4',
            'amount_paid' => 'decimal:4',
            'balance_due' => 'decimal:4',
            'approved_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BillLine::class)->orderBy('line_no');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'allocatable_id')->where('allocatable_type', 'bill');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * Approved/PartiallyPaid with a due date before today.
     *
     * @param  Builder<Bill>  $query
     */
    public function scopeOverdue(Builder $query, ?string $asOf = null): void
    {
        $query->whereIn('bills.status', [BillStatus::Approved->value, BillStatus::PartiallyPaid->value])
            ->whereDate('bills.due_date', '<', $asOf ?? now()->toDateString());
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    public function isOverdue(): bool
    {
        return $this->status->isOpen() && $this->due_date->lt(now()->startOfDay());
    }

    public function allocatableType(): string
    {
        return 'bill';
    }

    public function partnerType(): PaymentPartnerType
    {
        return PaymentPartnerType::Supplier;
    }

    public function partnerId(): int
    {
        return (int) $this->supplier_id;
    }

    public function acceptsPayments(): bool
    {
        return $this->status->isOpen() && Money::compare((string) $this->balance_due, '0') > 0;
    }

    public function outstanding(): string
    {
        return Money::normalize((string) $this->balance_due);
    }

    public function applyPayment(string $amount): void
    {
        $this->amount_paid = Money::add((string) $this->amount_paid, $amount);
        $this->balance_due = Money::sub((string) $this->total, (string) $this->amount_paid);
        $this->status = $this->settlementStatus();
    }

    public function revertPayment(string $amount): void
    {
        $this->amount_paid = Money::sub((string) $this->amount_paid, $amount);
        $this->balance_due = Money::sub((string) $this->total, (string) $this->amount_paid);
        $this->status = $this->settlementStatus();
    }

    private function settlementStatus(): BillStatus
    {
        return match (true) {
            Money::compare((string) $this->balance_due, '0') <= 0 => BillStatus::Paid,
            Money::compare((string) $this->amount_paid, '0') > 0 => BillStatus::PartiallyPaid,
            default => BillStatus::Approved,
        };
    }
}
