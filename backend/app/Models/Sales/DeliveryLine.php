<?php

namespace App\Models\Sales;

use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Sales\DeliveryLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'delivery_id', 'sales_order_line_id', 'quantity'])]
class DeliveryLine extends Model
{
    /** @use HasFactory<DeliveryLineFactory> */
    use BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4'];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function salesOrderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class);
    }
}
