<?php

namespace App\Actions\Payroll;

use App\Models\Payroll\SalaryStructure;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a salary structure; `components` (when present) replaces
 * the structure's component set.
 */
class SaveSalaryStructureAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?SalaryStructure $structure = null): SalaryStructure
    {
        return DB::transaction(function () use ($data, $structure): SalaryStructure {
            $attributes = array_intersect_key($data, array_flip(['name', 'description', 'currency', 'is_active']));

            if ($structure === null) {
                $structure = SalaryStructure::query()->create($attributes);
            } elseif ($attributes !== []) {
                $structure->update($attributes);
            }

            if (array_key_exists('components', $data)) {
                $structure->components()->delete();

                foreach ((array) $data['components'] as $row) {
                    $structure->components()->create([
                        'company_id' => $structure->company_id,
                        'pay_component_id' => (int) $row['pay_component_id'],
                        'amount' => isset($row['amount']) ? Money::normalize($row['amount']) : null,
                        'rate' => isset($row['rate']) ? Money::normalize($row['rate']) : null,
                    ]);
                }
            }

            return $structure->load('components.component');
        });
    }
}
