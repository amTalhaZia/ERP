<?php

namespace App\Models\Finance;

use App\Enums\Finance\AccountType;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Finance\AccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Chart-of-accounts node. Natural balance follows the type: Asset/Expense are
 * debit-normal, Liability/Equity/Revenue credit-normal. System accounts are
 * seeded per company and referenced by code from sub-ledgers (1100 AR, 2000 AP…).
 */
#[Fillable([
    'company_id', 'parent_id', 'code', 'name', 'type', 'subtype', 'is_bank', 'is_cash', 'is_system',
    'is_active', 'currency', 'description', 'opening_balance', 'opening_balance_date',
])]
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'is_bank' => 'boolean',
            'is_cash' => 'boolean',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'opening_balance' => 'decimal:4',
            'opening_balance_date' => 'date',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function isDebitNormal(): bool
    {
        return $this->type->isDebitNormal();
    }

    public function isCashOrBank(): bool
    {
        return $this->is_cash || $this->is_bank;
    }

    public function hasJournalLines(): bool
    {
        return JournalLine::withoutTenancy()->where('account_id', $this->id)->exists();
    }
}
