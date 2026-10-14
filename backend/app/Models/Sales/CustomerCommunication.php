<?php

namespace App\Models\Sales;

use App\Enums\Sales\CommunicationType;
use App\Models\User;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Sales\CustomerCommunicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'customer_id', 'user_id', 'type', 'subject', 'body', 'happened_at', 'follow_up_at'])]
class CustomerCommunication extends Model
{
    /** @use HasFactory<CustomerCommunicationFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'type' => CommunicationType::class,
            'happened_at' => 'datetime',
            'follow_up_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
