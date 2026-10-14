<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\Account;
use App\Support\Tenancy\Rules\AccessibleBranch;
use App\Support\Tenancy\Rules\TenantExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared parameters of the financial reports. Range reports default to the
 * current month; point-in-time reports default to today.
 */
class ReportRequest extends FormRequest
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
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('from'), 'after_or_equal:from')],
            'as_of' => ['nullable', 'date_format:Y-m-d'],
            'branch_id' => ['nullable', 'integer', new AccessibleBranch],
            'compare' => ['nullable', 'in:true,false,1,0'],
            'include_zero' => ['nullable', 'in:true,false,1,0'],
            'account_id' => ['nullable', 'integer', new TenantExists(Account::class)],
        ];
    }

    public function from(): string
    {
        return (string) ($this->validated('from') ?? now()->startOfMonth()->toDateString());
    }

    public function to(): string
    {
        return (string) ($this->validated('to') ?? now()->toDateString());
    }

    public function asOf(): string
    {
        return (string) ($this->validated('as_of') ?? now()->toDateString());
    }

    public function branchId(): ?int
    {
        $value = $this->validated('branch_id');

        return $value === null ? null : (int) $value;
    }

    public function compare(): bool
    {
        return $this->boolean('compare');
    }
}
