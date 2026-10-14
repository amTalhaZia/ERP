<?php

namespace App\Models\Finance;

use App\Enums\Finance\JournalEntryStatus;
use App\Exceptions\Finance\FinanceException;
use App\Models\Branch;
use App\Models\User;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Finance\JournalEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * General-ledger document. Once posted it is immutable: the only permitted
 * change is being marked Reversed by a reversing entry (status +
 * reversed_by_entry_id). Deleting is restricted to drafts.
 */
#[Fillable([
    'company_id', 'branch_id', 'financial_period_id', 'number', 'entry_date', 'reference', 'memo', 'status',
    'source_type', 'source_id', 'total_debit', 'total_credit', 'created_by', 'posted_by', 'posted_at',
    'reverses_entry_id', 'reversed_by_entry_id',
])]
class JournalEntry extends Model
{
    /** @use HasFactory<JournalEntryFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    /** Attributes a Posted entry may still change (when it is being reversed). */
    private const REVERSAL_LINK_FIELDS = ['status', 'reversed_by_entry_id', 'updated_at'];

    protected static function booted(): void
    {
        static::updating(function (JournalEntry $entry): void {
            $entry->guardImmutability();
        });

        static::deleting(function (JournalEntry $entry): void {
            if ($entry->status !== JournalEntryStatus::Draft) {
                throw FinanceException::immutable();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'status' => JournalEntryStatus::class,
            'total_debit' => 'decimal:4',
            'total_credit' => 'decimal:4',
            'posted_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class)->orderBy('line_no');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'financial_period_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reverses_entry_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversed_by_entry_id');
    }

    public function isDraft(): bool
    {
        return $this->status === JournalEntryStatus::Draft;
    }

    public function isPosted(): bool
    {
        return $this->status === JournalEntryStatus::Posted;
    }

    /**
     * Posted / Reversed entries only accept the Posted → Reversed link update.
     */
    private function guardImmutability(): void
    {
        $original = JournalEntryStatus::tryFrom((string) $this->getRawOriginal('status'));

        if ($original === null || $original === JournalEntryStatus::Draft) {
            return;
        }

        $dirty = array_keys($this->getDirty());
        $illegal = array_diff($dirty, self::REVERSAL_LINK_FIELDS);
        $becomesReversed = $original === JournalEntryStatus::Posted
            && (! $this->isDirty('status') || $this->status === JournalEntryStatus::Reversed);

        if ($illegal !== [] || ! $becomesReversed) {
            throw FinanceException::immutable();
        }
    }
}
