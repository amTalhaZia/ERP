<?php

namespace App\Models\Finance;

use App\Enums\Finance\InvoiceStatus;
use App\Enums\Finance\PaymentPartnerType;
use App\Models\Branch;
use App\Models\User;
use App\Services\Finance\Receivables\Settleable;
use App\Support\Audit\Auditable;
use App\Support\Money\Money;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Finance\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Customer invoice (AR sub-ledger). Approval posts it to the GL; from then on
 * it is immutable except for payment settlement fields and voiding.
 * customer_id / sales_order_id reference the Sales module without FKs.
 */
#[Fillable([
    'company_id', 'branch_id', 'number', 'customer_id', 'sales_order_id', 'invoice_date', 'due_date', 'currency',
    'status', 'subtotal', 'discount_total', 'tax_total', 'total', 'amount_paid', 'balance_due', 'notes', 'terms',
    'journal_entry_id', 'created_by', 'approved_by', 'approved_at', 'voided_at', 'void_reason',
])]
class Invoice extends Model implements Settleable
{
    /** @use HasFactory<InvoiceFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'status' => InvoiceStatus::class,
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
        return $this->hasMany(InvoiceLine::class)->orderBy('line_no');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'allocatable_id')->where('allocatable_type', 'invoice');
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
     * @param  Builder<Invoice>  $query
     */
    public function scopeOverdue(Builder $query, ?string $asOf = null): void
    {
        $query->whereIn('invoices.status', [InvoiceStatus::Approved->value, InvoiceStatus::PartiallyPaid->value])
            ->whereDate('invoices.due_date', '<', $asOf ?? now()->toDateString());
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
        return 'invoice';
    }

    public function partnerType(): PaymentPartnerType
    {
        return PaymentPartnerType::Customer;
    }

    public function partnerId(): int
    {
        return (int) $this->customer_id;
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

    private function settlementStatus(): InvoiceStatus
    {
        return match (true) {
            Money::compare((string) $this->balance_due, '0') <= 0 => InvoiceStatus::Paid,
            Money::compare((string) $this->amount_paid, '0') > 0 => InvoiceStatus::PartiallyPaid,
            default => InvoiceStatus::Approved,
        };
    }
}
