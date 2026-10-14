<?php

namespace App\Models\Payroll;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A component on a salary structure; amount / rate override the component
 * defaults when set.
 */
#[Fillable(['company_id', 'salary_structure_id', 'pay_component_id', 'amount', 'rate'])]
class SalaryStructureComponent extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return ['amount' => 'decimal:4', 'rate' => 'decimal:4'];
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class, 'salary_structure_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(PayComponent::class, 'pay_component_id');
    }
}
