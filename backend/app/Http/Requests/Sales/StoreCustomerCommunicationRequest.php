<?php

namespace App\Http\Requests\Sales;

use App\Enums\Sales\CommunicationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerCommunicationRequest extends FormRequest
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
            'type' => [$required, Rule::enum(CommunicationType::class)],
            'subject' => [$required, 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:10000'],
            'happened_at' => ['sometimes', 'date'],
            'follow_up_at' => ['nullable', 'date'],
        ];
    }
}
