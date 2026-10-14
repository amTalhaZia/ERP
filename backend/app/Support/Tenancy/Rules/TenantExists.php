<?php

namespace App\Support\Tenancy\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

/**
 * Validates that a referenced id exists AND belongs to the current tenant.
 *
 *   'customer_id' => ['required', new TenantExists(Customer::class)]
 *   'branch_id'   => ['nullable', new TenantExists(Branch::class, where: ['is_active' => true])]
 *
 * Because the model carries the CompanyScope, a foreign company's id is simply
 * "not found", so the client learns nothing about other tenants.
 */
class TenantExists implements ValidationRule
{
    /**
     * @param  class-string<Model>  $model
     * @param  array<string, mixed>  $where
     */
    public function __construct(
        private readonly string $model,
        private readonly string $column = 'id',
        private readonly array $where = [],
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $query = $this->model::query()->where($this->column, $value);

        foreach ($this->where as $col => $val) {
            $query->where($col, $val);
        }

        if (! $query->exists()) {
            $fail('The selected :attribute is invalid.');
        }
    }
}
