<?php

namespace App\Http\Requests\Finance\Receivables;

use Illuminate\Foundation\Http\FormRequest;

/** Void (invoice / bill / payment) and reject (expense claim) need a reason. */
class ReasonRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
