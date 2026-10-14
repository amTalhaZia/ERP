<?php

namespace App\Models\Sales;

use App\Enums\Sales\LeadSource;
use App\Enums\Sales\LeadStatus;
use App\Models\User;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Sales\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'company_id', 'name', 'company_name', 'email', 'phone', 'source', 'status', 'estimated_value',
    'assigned_to', 'notes', 'converted_customer_id', 'converted_at',
])]
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use Auditable, BelongsToCompany, HasFactory, SoftDeletes;

    /** @var array<string, mixed> mirrors the column defaults so fresh models serialise fully */
    protected $attributes = [
        'source' => 'other',
        'status' => 'new',
    ];

    protected function casts(): array
    {
        return [
            'source' => LeadSource::class,
            'status' => LeadStatus::class,
            'estimated_value' => 'decimal:4',
            'converted_at' => 'datetime',
        ];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function convertedCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'converted_customer_id');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }
}
