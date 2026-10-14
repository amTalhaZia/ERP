<?php

namespace App\Models\Sales;

use App\Enums\Sales\QuotationStatus;
use App\Models\User;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Sales\QuotationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'company_id', 'number', 'customer_id', 'date', 'valid_until', 'status', 'currency',
    'subtotal', 'discount_total', 'tax_total', 'total', 'notes', 'terms', 'rejection_reason',
    'sales_rep_id', 'created_by', 'converted_sales_order_id', 'sent_at', 'accepted_at', 'rejected_at',
])]
class Quotation extends Model
{
    /** @use HasFactory<QuotationFactory> */
    use Auditable, BelongsToCompany, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'valid_until' => 'date',
            'status' => QuotationStatus::class,
            'subtotal' => 'decimal:4',
            'discount_total' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'total' => 'decimal:4',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    /**
     * Lazily expire Sent quotations whose validity has passed (tenant-scoped
     * bulk update, no per-row model events).
     */
    public static function expireStale(): int
    {
        return static::query()
            ->where('status', QuotationStatus::Sent)
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<', today())
            ->update(['status' => QuotationStatus::Expired, 'updated_at' => now()]);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuotationLine::class)->orderBy('line_no');
    }

    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_rep_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function convertedSalesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'converted_sales_order_id');
    }
}
