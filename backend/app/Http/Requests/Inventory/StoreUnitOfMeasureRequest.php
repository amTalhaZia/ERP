<?php

namespace App\Http\Requests\Inventory;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitOfMeasureRequest extends FormRequest
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
        $companyId = app(TenantContext::class)->id();
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:60'],
            'symbol' => [
                $required, 'string', 'max:12',
                Rule::unique('units_of_measure', 'symbol')->where('company_id', $companyId)->ignore($this->route('unit_of_measure')?->id),
            ],
            'precision' => ['sometimes', 'integer', 'between:0,4'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
