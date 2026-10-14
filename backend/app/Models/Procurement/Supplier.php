<?php

namespace App\Models\Procurement;

use App\Enums\Procurement\SupplierType;
use App\Models\User;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Procurement\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'company_id', 'code', 'name', 'type', 'email', 'phone', 'website', 'tax_number', 'currency',
    'payment_terms_days', 'credit_limit', 'address_line1', 'address_line2', 'city', 'state', 'postal_code',
    'country', 'latitude', 'longitude', 'bank_name', 'bank_account_number', 'bank_account_last4', 'rating',
    'lead_time_days', 'categories', 'notes', 'is_active', 'is_approved', 'approved_by', 'approved_at',
])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use Auditable, BelongsToCompany, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => SupplierType::class,
            'payment_terms_days' => 'integer',
            'credit_limit' => 'decimal:4',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'bank_account_number' => 'encrypted',
            'rating' => 'integer',
            'lead_time_days' => 'integer',
            'categories' => 'array',
            'is_active' => 'boolean',
            'is_approved' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
