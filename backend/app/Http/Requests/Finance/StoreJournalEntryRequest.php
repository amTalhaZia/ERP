<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\Account;
use App\Models\Finance\TaxRate;
use App\Support\Tenancy\Rules\AccessibleBranch;
use App\Support\Tenancy\Rules\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Manual journal entries. Balance is checked at posting time (an unbalanced
 * draft is allowed to be saved, never posted); line shape is checked here and
 * again in the action so LedgerPoster gets the same guarantees.
 */
class StoreJournalEntryRequest extends FormRequest
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
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        $amount = ['nullable', 'numeric', 'min:0', 'regex:/^\d{1,14}(\.\d{1,4})?$/'];

        return [
            'entry_date' => [$required, 'date_format:Y-m-d'],
            'reference' => ['nullable', 'string', 'max:100'],
            'memo' => ['nullable', 'string', 'max:500'],
            'branch_id' => ['nullable', 'integer', new AccessibleBranch],
            'lines' => [$required, 'array', 'min:2', 'max:200'],
            'lines.*.account_id' => ['required', 'integer', new TenantExists(Account::class)],
            'lines.*.debit' => $amount,
            'lines.*.credit' => $amount,
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.branch_id' => ['nullable', 'integer', new AccessibleBranch],
            'lines.*.partner_type' => ['nullable', 'string', 'max:30', 'alpha_dash'],
            'lines.*.partner_id' => ['nullable', 'integer', 'required_with:lines.*.partner_type'],
            'lines.*.tax_rate_id' => ['nullable', 'integer', new TenantExists(TaxRate::class)],
        ];
    }
}
