<?php

namespace App\Http\Requests\HR;

use App\Support\Tenancy\Rules\AccessibleBranch;
use Illuminate\Foundation\Http\FormRequest;

class StoreHolidayRequest extends FormRequest
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

        return [
            'name' => [$required, 'string', 'max:150'],
            'date' => [$required, 'date'],
            'is_recurring' => ['sometimes', 'boolean'],
            'branch_id' => ['nullable', 'integer', new AccessibleBranch],
        ];
    }
}
