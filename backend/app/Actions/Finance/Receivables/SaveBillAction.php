<?php

namespace App\Actions\Finance\Receivables;

use App\Actions\Finance\Receivables\Concerns\BuildsDocumentLines;
use App\Enums\Finance\BillStatus;
use App\Models\Finance\Bill;
use App\Services\Finance\ChartOfAccountsTemplate;
use App\Services\Finance\Receivables\DocumentTotalsCalculator;
use App\Services\Finance\Receivables\PartnerDirectory;
use App\Support\Numbering\DocumentNumberer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a Draft / PendingApproval supplier bill. Lines, when given,
 * replace the existing ones; totals are always recomputed server-side.
 * Default due date = bill_date + supplier payment_terms_days (30 if unknown).
 * Default line account: 1200 Inventory when product_id is set, else 7900 Misc.
 */
class SaveBillAction
{
    use BuildsDocumentLines;

    private const HEADER = ['supplier_id', 'purchase_order_id', 'bill_date', 'due_date', 'currency', 'notes', 'supplier_reference', 'branch_id'];

    public function __construct(
        private readonly DocumentNumberer $numberer,
        private readonly DocumentTotalsCalculator $calculator,
        private readonly PartnerDirectory $partners,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?Bill $bill = null, ?int $userId = null): Bill
    {
        if ($bill !== null) {
            $this->refuseUnlessEditable($bill->isEditable(), "Bill {$bill->number}");
        }

        return DB::transaction(function () use ($data, $bill, $userId): Bill {
            $header = Arr::only($data, self::HEADER);
            $supplierId = (int) ($header['supplier_id'] ?? $bill?->supplier_id);
            $terms = $this->partners->terms('suppliers', $supplierId);

            if ($bill === null) {
                $bill = new Bill([
                    'number' => $this->numberer->next('bill'),
                    'status' => BillStatus::Draft,
                    'created_by' => $userId,
                    'currency' => $terms['currency'] ?? $this->tenant->company()?->currency ?? 'USD',
                ]);
            }

            $bill->fill(array_filter(
                Arr::except($header, ['due_date']),
                fn ($value, $key) => $value !== null || ! in_array($key, ['supplier_id', 'bill_date', 'currency'], true),
                ARRAY_FILTER_USE_BOTH,
            ));

            if (($header['due_date'] ?? null) !== null) {
                $bill->due_date = $header['due_date'];
            } elseif ($bill->due_date === null || array_key_exists('bill_date', $header)) {
                $bill->due_date = Carbon::parse($bill->bill_date)->addDays($terms['payment_terms_days'] ?? 30)->toDateString();
            }

            $bill->save();

            if (array_key_exists('lines', $data)) {
                $inventoryId = $this->accountIdByCode(ChartOfAccountsTemplate::INVENTORY);
                $miscId = $this->accountIdByCode(ChartOfAccountsTemplate::MISCELLANEOUS);
                $rows = $this->buildLines(
                    $data['lines'],
                    fn (array $line) => ($line['product_id'] ?? null) !== null ? $inventoryId : $miscId,
                );

                $bill->lines()->delete();
                $bill->lines()->createMany($rows);
            }

            $totals = $this->calculator->totals($bill->lines()->get()->map(fn ($line) => [
                'line_subtotal' => (string) $line->line_subtotal,
                'discount_amount' => (string) $line->discount_amount,
                'tax_amount' => (string) $line->tax_amount,
            ]));

            $bill->fill($totals + ['amount_paid' => '0', 'balance_due' => $totals['total']])->save();

            return $bill->refresh();
        });
    }
}
