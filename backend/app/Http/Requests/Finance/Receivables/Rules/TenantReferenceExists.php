<?php

namespace App\Http\Requests\Finance\Receivables\Rules;

use App\Services\Finance\Receivables\PartnerDirectory;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Schema;

/**
 * TenantExists for tables owned by other modules (customers, suppliers,
 * sales_orders, purchase_orders, products, employees) without depending on
 * their Eloquent models: a raw `company_id = tenant AND id = ?` query.
 *
 * With `optionalModule: true` the check is skipped when the owning module's
 * table does not exist (soft references like product_id / employee_id).
 */
class TenantReferenceExists implements ValidationRule
{
    public function __construct(
        private readonly string $table,
        private readonly bool $optionalModule = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if ($this->optionalModule && ! Schema::hasTable($this->table)) {
            return;
        }

        if (! app(PartnerDirectory::class)->exists($this->table, $value)) {
            $fail('The selected :attribute is invalid.');
        }
    }
}
