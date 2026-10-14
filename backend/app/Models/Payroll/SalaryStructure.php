<?php

namespace App\Models\Payroll;

use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Payroll\SalaryStructureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'name', 'description', 'currency', 'is_active'])]
class SalaryStructure extends Model
{
    /** @use HasFactory<SalaryStructureFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function components(): HasMany
    {
        return $this->hasMany(SalaryStructureComponent::class)->orderBy('id');
    }

    public function salaries(): HasMany
    {
        return $this->hasMany(EmployeeSalary::class);
    }
}
