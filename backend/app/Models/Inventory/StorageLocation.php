<?php

namespace App\Models\Inventory;

use App\Enums\Inventory\StorageLocationType;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Inventory\StorageLocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'warehouse_id', 'parent_id', 'code', 'name', 'type', 'is_active'])]
class StorageLocation extends Model
{
    /** @use HasFactory<StorageLocationFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'type' => StorageLocationType::class,
            'is_active' => 'boolean',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(StorageLocation::class, 'parent_id');
    }
}
