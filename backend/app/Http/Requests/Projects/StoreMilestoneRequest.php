<?php

namespace App\Http\Requests\Projects;

use App\Enums\Projects\MilestoneStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMilestoneRequest extends FormRequest
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
            'name' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'due_date' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::enum(MilestoneStatus::class)],
            'position' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
