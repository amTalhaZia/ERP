<?php

namespace App\Models\Finance;

use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Finance\BankAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Operational bank/cash account linked to a GL account flagged is_bank/is_cash.
 * Account number and IBAN are encrypted at rest; only the last 4 digits are
 * stored in clear for display and search.
 */
#[Fillable([
    'company_id', 'account_id', 'name', 'bank_name', 'account_number', 'iban', 'swift',
    'currency', 'opening_balance', 'is_active',
])]
class BankAccount extends Model
{
    /** @use HasFactory<BankAccountFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    /** @var array<int, string> */
    protected array $auditExclude = ['account_number', 'iban'];

    protected static function booted(): void
    {
        static::saving(function (BankAccount $bank): void {
            $number = $bank->account_number;
            $bank->account_number_last4 = $number === null || $number === '' ? null : substr($number, -4);
        });
    }

    protected function casts(): array
    {
        return [
            'account_number' => 'encrypted',
            'iban' => 'encrypted',
            'opening_balance' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function maskedAccountNumber(): ?string
    {
        return $this->account_number_last4 === null ? null : '••••'.$this->account_number_last4;
    }
}
