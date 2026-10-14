<?php

namespace App\Models\Inventory;

use App\Enums\Inventory\ProductType;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Inventory\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Catalogue item. Quantity on hand is never stored here: aggregate
 * `stockLevels` (withSum) — stock_movements is the source of truth.
 */
#[Fillable([
    'company_id', 'category_id', 'uom_id', 'sku', 'barcode', 'name', 'description', 'type',
    'cost_price', 'sale_price', 'currency', 'tax_rate', 'reorder_level', 'reorder_quantity',
    'track_batches', 'track_serials', 'is_active', 'image_path', 'attributes',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use Auditable, BelongsToCompany, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'cost_price' => 'decimal:4',
            'sale_price' => 'decimal:4',
            'tax_rate' => 'decimal:4',
            'reorder_level' => 'decimal:4',
            'reorder_quantity' => 'decimal:4',
            'track_batches' => 'boolean',
            'track_serials' => 'boolean',
            'is_active' => 'boolean',
            'attributes' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function serialNumbers(): HasMany
    {
        return $this->hasMany(SerialNumber::class);
    }

    public function isGoods(): bool
    {
        return $this->type === ProductType::Goods;
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }
}
