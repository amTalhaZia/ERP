<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class TerminateEmployeeRequest extends FormRequest
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
            'terminated_at' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
