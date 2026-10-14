<?php

namespace App\Models\Sales;

use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Sales\CustomerContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'customer_id', 'name', 'title', 'email', 'phone', 'is_primary'])]
class CustomerContact extends Model
{
    /** @use HasFactory<CustomerContactFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = ['is_primary' => false];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
