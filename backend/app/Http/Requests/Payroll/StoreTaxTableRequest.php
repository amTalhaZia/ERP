<?php

namespace App\Http\Requests\Payroll;

use App\Enums\Payroll\TaxTablePeriod;
use App\Support\Money\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Tax table with nested brackets (replaced on save). Brackets must not
 * overlap; an open-ended bracket (no to_amount) must be the highest.
 */
class StoreTaxTableRequest extends FormRequest
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
            'name' => [$required, 'string', 'max:100'],
            'effective_from' => [$required, 'date_format:Y-m-d'],
            'period' => [$required, Rule::enum(TaxTablePeriod::class)],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'brackets' => [$required, 'array', 'min:1', 'max:50'],
            'brackets.*.from_amount' => ['required', 'numeric', 'min:0', StorePayComponentRequest::MONEY],
            'brackets.*.to_amount' => ['nullable', 'numeric', 'min:0', StorePayComponentRequest::MONEY],
            'brackets.*.rate' => ['required', 'numeric', 'min:0', 'max:100', StorePayComponentRequest::RATE],
            'brackets.*.fixed_amount' => ['sometimes', 'numeric', 'min:0', StorePayComponentRequest::MONEY],
        ];
    }

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $brackets = $this->input('brackets');

            if (! is_array($brackets) || $validator->errors()->isNotEmpty()) {
                return;
            }

            ksort($brackets);
            $rows = array_values($brackets);
            usort($rows, fn (array $a, array $b) => Money::compare(Money::normalize($a['from_amount']), Money::normalize($b['from_amount'])));

            foreach ($rows as $i => $row) {
                $to = $row['to_amount'] ?? null;

                if ($to !== null && $to !== '' && Money::compare(Money::normalize($to), Money::normalize($row['from_amount'])) <= 0) {
                    $validator->errors()->add('brackets', 'Each bracket\'s to_amount must be greater than its from_amount.');

                    return;
                }

                $next = $rows[$i + 1] ?? null;

                if ($next !== null && ($to === null || $to === '' || Money::compare(Money::normalize($next['from_amount']), Money::normalize($to)) < 0)) {
                    $validator->errors()->add('brackets', 'Brackets must not overlap; only the highest bracket may be open-ended.');

                    return;
                }
            }
        }];
    }
}
