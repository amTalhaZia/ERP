<?php

namespace App\Models\Finance;

use App\Enums\Finance\ExpenseClaimStatus;
use App\Enums\Finance\PaymentPartnerType;
use App\Models\Branch;
use App\Models\User;
use App\Services\Finance\Receivables\Settleable;
use App\Support\Audit\Auditable;
use App\Support\Money\Money;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Finance\ExpenseClaimFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Employee expense claim. Approval posts Dr expense / Cr 2300 Accrued
 * Liabilities with the submitting user as the `employee` partner; it is then
 * settled by a Payment (partner_type employee, partner_id = user_id).
 * employee_id references the HR module without an FK.
 */
#[Fillable([
    'company_id', 'branch_id', 'number', 'employee_id', 'user_id', 'claim_date', 'currency', 'status',
    'subtotal', 'tax_total', 'total', 'amount_paid', 'balance_due', 'notes', 'submitted_at', 'approver_id',
    'approved_at', 'rejection_reason', 'journal_entry_id',
])]
class ExpenseClaim extends Model implements Settleable
{
    /** @use HasFactory<ExpenseClaimFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'claim_date' => 'date',
            'status' => ExpenseClaimStatus::class,
            'subtotal' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'total' => 'decimal:4',
            'amount_paid' => 'decimal:4',
            'balance_due' => 'decimal:4',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ExpenseClaimLine::class)->orderBy('line_no');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function isDraft(): bool
    {
        return $this->status === ExpenseClaimStatus::Draft;
    }

    public function isOwnedBy(User $user): bool
    {
        return (int) $this->user_id === (int) $user->id;
    }

    public function allocatableType(): string
    {
        return 'expense_claim';
    }

    public function partnerType(): PaymentPartnerType
    {
        return PaymentPartnerType::Employee;
    }

    public function partnerId(): int
    {
        return (int) $this->user_id;
    }

    public function acceptsPayments(): bool
    {
        return $this->status === ExpenseClaimStatus::Approved && Money::compare((string) $this->balance_due, '0') > 0;
    }

    public function outstanding(): string
    {
        return Money::normalize((string) $this->balance_due);
    }

    public function applyPayment(string $amount): void
    {
        $this->amount_paid = Money::add((string) $this->amount_paid, $amount);
        $this->syncSettlement();
    }

    public function revertPayment(string $amount): void
    {
        $this->amount_paid = Money::sub((string) $this->amount_paid, $amount);
        $this->syncSettlement();
    }

    private function syncSettlement(): void
    {
        $this->balance_due = Money::sub((string) $this->total, (string) $this->amount_paid);
        $this->status = Money::compare((string) $this->balance_due, '0') <= 0
            ? ExpenseClaimStatus::Paid
            : ExpenseClaimStatus::Approved;
    }
}
