<?php

namespace App\Actions\Finance\Receivables;

use App\Enums\Finance\ExpenseClaimStatus;
use App\Exceptions\Finance\FinanceException;
use App\Models\Finance\ExpenseClaim;
use App\Models\Finance\ExpenseClaimLine;
use App\Models\Finance\TaxRate;
use App\Services\Finance\Receivables\DocumentTotalsCalculator;
use App\Support\Money\Money;
use App\Support\Numbering\DocumentNumberer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates the submitter's own Draft (or Rejected → back to Draft)
 * expense claim. Lines, when given, are upserted by `id` (receipts of kept
 * lines survive); omitted lines are deleted together with their receipt file. Totals are computed server-side.
 */
class SaveExpenseClaimAction
{
    private const HEADER = ['employee_id', 'claim_date', 'currency', 'notes', 'branch_id'];

    public function __construct(
        private readonly DocumentNumberer $numberer,
        private readonly DocumentTotalsCalculator $calculator,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?ExpenseClaim $claim, int $userId): ExpenseClaim
    {
        if ($claim !== null && ! in_array($claim->status, [ExpenseClaimStatus::Draft, ExpenseClaimStatus::Rejected], true)) {
            throw FinanceException::conflict("Expense claim {$claim->number} is {$claim->status->value} and can no longer be changed.");
        }

        $obsoleteReceipts = [];

        $saved = DB::transaction(function () use ($data, $claim, $userId, &$obsoleteReceipts): ExpenseClaim {
            if ($claim === null) {
                $claim = new ExpenseClaim([
                    'number' => $this->numberer->next('expense_claim'),
                    'user_id' => $userId,
                    'status' => ExpenseClaimStatus::Draft,
                    'currency' => $this->tenant->company()?->currency ?? 'USD',
                ]);
            }

            $header = array_filter(
                Arr::only($data, self::HEADER),
                fn ($value, $key) => $value !== null || in_array($key, ['employee_id', 'notes', 'branch_id'], true),
                ARRAY_FILTER_USE_BOTH,
            );

            $claim->fill($header + ['status' => ExpenseClaimStatus::Draft, 'rejection_reason' => null])->save();

            if (array_key_exists('lines', $data)) {
                $obsoleteReceipts = $this->syncLines($claim, $data['lines']);
            }

            $lines = $claim->lines()->get();
            $subtotal = Money::sum($lines->map(fn ($line) => (string) $line->amount)->all());
            $tax = Money::sum($lines->map(fn ($line) => (string) $line->tax_amount)->all());
            $total = Money::add($subtotal, $tax);

            $claim->fill([
                'subtotal' => $subtotal,
                'tax_total' => $tax,
                'total' => $total,
                'amount_paid' => '0',
                'balance_due' => $total,
            ])->save();

            return $claim->refresh();
        });

        if ($obsoleteReceipts !== []) {
            Storage::disk('local')->delete($obsoleteReceipts);
        }

        return $saved;
    }

    /**
     * Upserts the claim's lines: an input line carrying the `id` of an existing
     * line updates it in place (its receipt survives), lines without an id are
     * created, and existing lines omitted from the input are deleted. Returns
     * the receipt paths of the deleted lines (removed from disk after commit).
     *
     * @param  array<int, array<string, mixed>>  $input
     * @return list<string>
     */
    private function syncLines(ExpenseClaim $claim, array $input): array
    {
        $existing = $claim->lines()->get()->keyBy('id');
        $kept = [];

        foreach ($this->buildLines($input) as $index => $row) {
            $id = $row['id'];
            unset($row['id']);

            if ($id === null) {
                $claim->lines()->create($row);

                continue;
            }

            /** @var ExpenseClaimLine|null $line */
            $line = $existing->get($id);

            if ($line === null || in_array($id, $kept, true)) {
                throw ValidationException::withMessages([
                    "lines.{$index}.id" => 'The line does not belong to this expense claim.',
                ]);
            }

            $line->fill($row)->save();
            $kept[] = $id;
        }

        $removed = $existing->except($kept);

        if ($removed->isNotEmpty()) {
            ExpenseClaimLine::query()->whereKey($removed->modelKeys())->delete();
        }

        return $removed->pluck('receipt_path')->filter()->values()->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function buildLines(array $lines): array
    {
        // validated() can reorder keyed wildcard arrays; restore input order.
        ksort($lines);

        $rates = TaxRate::query()->whereIn('id', array_filter(array_column($lines, 'tax_rate_id')))->get()->keyBy('id');
        $rows = [];

        foreach (array_values($lines) as $index => $line) {
            $rate = isset($line['tax_rate_id']) ? $rates->get((int) $line['tax_rate_id']) : null;
            $rateValue = $rate ? (string) $rate->rate : '0';
            $net = Money::normalize(Money::round(Money::normalize((string) $line['amount']), DocumentTotalsCalculator::DECIMALS));

            $rows[] = [
                'id' => isset($line['id']) && $line['id'] !== '' ? (int) $line['id'] : null,
                'line_no' => $index + 1,
                'expense_date' => $line['expense_date'],
                'category' => $line['category'],
                'account_id' => (int) $line['account_id'],
                'description' => $line['description'] ?? null,
                'amount' => $net,
                'tax_rate_id' => $rate?->id,
                'tax_rate' => Money::normalize($rateValue),
            ] + $this->calculator->amountWithTax($net, $rateValue);
        }

        return $rows;
    }
}
