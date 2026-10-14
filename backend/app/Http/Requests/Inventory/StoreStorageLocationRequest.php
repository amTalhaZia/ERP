<?php

namespace App\Http\Requests\Inventory;

use App\Enums\Inventory\StorageLocationType;
use App\Models\Inventory\StorageLocation;
use App\Support\Tenancy\Rules\TenantExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Used for both `POST warehouses/{warehouse}/locations` and
 * `PUT locations/{location}`; the warehouse comes from whichever is bound.
 */
class StoreStorageLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var StorageLocation|null $location */
        $location = $this->route('location');
        $warehouseId = $this->route('warehouse')?->id ?? $location?->warehouse_id;
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'code' => [
                $required, 'string', 'max:30', 'alpha_dash',
                Rule::unique('storage_locations', 'code')->where('warehouse_id', $warehouseId)->ignore($location?->id),
            ],
            'name' => [$required, 'string', 'max:120'],
            'type' => ['sometimes', Rule::enum(StorageLocationType::class)],
            'parent_id' => [
                'nullable', 'integer',
                new TenantExists(StorageLocation::class, where: ['warehouse_id' => $warehouseId]),
                $location ? Rule::notIn([$location->id]) : 'nullable',
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
