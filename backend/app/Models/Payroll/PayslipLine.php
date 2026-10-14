<?php

namespace App\Models\Payroll;

use App\Enums\Payroll\PayComponentKind;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id', 'payslip_id', 'pay_component_id', 'code', 'name', 'kind', 'calculation', 'rate', 'amount',
    'is_taxable', 'affects_gross', 'gl_account_code', 'sort_order',
])]
class PayslipLine extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'kind' => PayComponentKind::class,
            'rate' => 'decimal:4',
            'amount' => 'decimal:4',
            'is_taxable' => 'boolean',
            'affects_gross' => 'boolean',
        ];
    }

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }
}
