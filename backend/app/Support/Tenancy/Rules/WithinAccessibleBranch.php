<?php

namespace App\Support\Tenancy\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

/**
 * The referenced record must be inside the acting user's branch restriction
 * (see RestrictsToAllowedBranches). Pair with TenantExists: this rule only
 * fails for records that exist but sit in a branch the user cannot access.
 *
 *   'employee_id' => ['required', new TenantExists(Employee::class), new WithinAccessibleBranch(Employee::class)]
 */
class WithinAccessibleBranch implements ValidationRule
{
    /**
     * @param  class-string<Model>  $model  a model using RestrictsToAllowedBranches
     */
    public function __construct(private readonly string $model) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '' || ! is_scalar($value)) {
            return;
        }

        $exists = $this->model::query()->whereKey($value)->exists();

        if ($exists && ! $this->model::query()->whereKey($value)->visibleBranches()->exists()) {
            $fail('The selected :attribute is invalid.');
        }
    }
}
