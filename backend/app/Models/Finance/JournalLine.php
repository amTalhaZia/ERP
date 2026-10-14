<?php

namespace App\Models\Finance;

use App\Enums\Finance\JournalEntryStatus;
use App\Exceptions\Finance\FinanceException;
use App\Models\Branch;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Finance\JournalLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One side of a posting. Exactly one of debit/credit is > 0 (DB CHECK).
 * Lines of a non-draft entry can neither be changed nor deleted.
 */
#[Fillable([
    'company_id', 'journal_entry_id', 'line_no', 'account_id', 'branch_id', 'partner_type', 'partner_id',
    'tax_rate_id', 'description', 'debit', 'credit',
])]
class JournalLine extends Model
{
    /** @use HasFactory<JournalLineFactory> */
    use BelongsToCompany, HasFactory;

    protected static function booted(): void
    {
        $guard = function (JournalLine $line): void {
            $status = JournalEntry::withoutTenancy()->whereKey($line->journal_entry_id)->value('status');

            if ($status !== null && $status !== JournalEntryStatus::Draft->value) {
                throw FinanceException::immutable('Lines of a posted journal entry');
            }
        };

        static::updating($guard);
        static::deleting($guard);
    }

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'debit' => 'decimal:4',
            'credit' => 'decimal:4',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }
}
