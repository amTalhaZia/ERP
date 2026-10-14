<?php

namespace App\Models\Payroll;

use App\Enums\Payroll\CalculationMethod;
use App\Enums\Payroll\PayComponentKind;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Payroll\PayComponentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Earning / deduction / employer-contribution catalogue entry.
 * Rates are percentages (7.5 = 7.5 %).
 */
#[Fillable([
    'company_id', 'code', 'name', 'description', 'kind', 'calculation', 'default_amount', 'default_rate',
    'is_taxable', 'reduces_taxable', 'affects_gross', 'gl_account_code', 'sort_order', 'is_active',
])]
class PayComponent extends Model
{
    /** @use HasFactory<PayComponentFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    /** Codes used for the synthetic basic-salary and withholding-tax payslip lines. */
    public const RESERVED_CODES = ['BASE', 'TAX'];

    protected function casts(): array
    {
        return [
            'kind' => PayComponentKind::class,
            'calculation' => CalculationMethod::class,
            'default_amount' => 'decimal:4',
            'default_rate' => 'decimal:4',
            'is_taxable' => 'boolean',
            'reduces_taxable' => 'boolean',
            'affects_gross' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function accountCode(): string
    {
        return $this->gl_account_code ?: $this->kind->defaultAccountCode();
    }

    public function isInUse(): bool
    {
        return SalaryStructureComponent::query()->where('pay_component_id', $this->id)->exists()
            || EmployeeSalaryComponent::query()->where('pay_component_id', $this->id)->exists();
    }
}
