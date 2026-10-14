<?php

namespace App\Models\Sales;

use App\Enums\Sales\SalesOrderStatus;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Sales\SalesOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * `warehouse_id` (Inventory, FK nullOnDelete) is the warehouse deliveries issue stock from;
 * orders without one move no stock on delivery.
 */
#[Fillable([
    'company_id', 'number', 'customer_id', 'quotation_id', 'order_date', 'expected_delivery_date', 'status', 'currency',
    'subtotal', 'discount_total', 'tax_total', 'total',
    'shipping_name', 'shipping_address_line1', 'shipping_address_line2', 'shipping_city', 'shipping_state',
    'shipping_postal_code', 'shipping_country', 'warehouse_id', 'notes', 'terms', 'sales_rep_id', 'created_by',
    'confirmed_at', 'approved_by', 'approved_at', 'cancelled_reason', 'cancelled_at',
])]
class SalesOrder extends Model
{
    /** @use HasFactory<SalesOrderFactory> */
    use Auditable, BelongsToCompany, HasFactory, SoftDeletes;

    /** @var array<int, string> */
    public const SHIPPING_FIELDS = [
        'shipping_name', 'shipping_address_line1', 'shipping_address_line2', 'shipping_city', 'shipping_state',
        'shipping_postal_code', 'shipping_country',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_delivery_date' => 'date',
            'status' => SalesOrderStatus::class,
            'subtotal' => 'decimal:4',
            'discount_total' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'total' => 'decimal:4',
            'confirmed_at' => 'datetime',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class)->orderBy('line_no');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_rep_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class)->withTrashed();
    }
}
