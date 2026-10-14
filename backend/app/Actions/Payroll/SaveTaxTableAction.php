<?php

namespace App\Actions\Payroll;

use App\Models\Payroll\TaxTable;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a tax table. When `brackets` is present the bracket set
 * is replaced wholesale (ordered by from_amount).
 */
class SaveTaxTableAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?TaxTable $table = null): TaxTable
    {
        return DB::transaction(function () use ($data, $table): TaxTable {
            $attributes = array_intersect_key($data, array_flip(['name', 'effective_from', 'period', 'is_active', 'notes']));

            if ($table === null) {
                $table = TaxTable::query()->create($attributes);
            } elseif ($attributes !== []) {
                $table->update($attributes);
            }

            if (array_key_exists('brackets', $data)) {
                $brackets = array_values((array) $data['brackets']);
                usort($brackets, fn (array $a, array $b) => Money::compare(Money::normalize($a['from_amount']), Money::normalize($b['from_amount'])));

                $table->brackets()->delete();

                foreach ($brackets as $i => $bracket) {
                    $table->brackets()->create([
                        'company_id' => $table->company_id,
                        'from_amount' => Money::normalize($bracket['from_amount']),
                        'to_amount' => isset($bracket['to_amount']) && $bracket['to_amount'] !== '' ? Money::normalize($bracket['to_amount']) : null,
                        'rate' => Money::normalize($bracket['rate']),
                        'fixed_amount' => Money::normalize($bracket['fixed_amount'] ?? '0'),
                        'sort_order' => $i + 1,
                    ]);
                }

                $this->audit->log('tax.tables.brackets_replaced', $table, null, null, ['bracket_count' => count($brackets)]);
            }

            return $table->load('brackets');
        });
    }
}
