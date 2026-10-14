<?php

namespace App\Actions\Finance\Receivables\Concerns;

use App\Exceptions\Finance\FinanceException;
use App\Models\Finance\Account;
use App\Models\Finance\TaxRate;
use App\Services\Finance\Receivables\DocumentTotalsCalculator;
use App\Support\Money\Money;
use Illuminate\Validation\ValidationException;

/**
 * Shared by invoice and bill drafts: turns validated line input into rows with
 * snapshotted tax rate and server-computed amounts.
 */
trait BuildsDocumentLines
{
    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @param  callable(array<string, mixed>): ?int  $defaultAccountId
     * @return list<array<string, mixed>>
     */
    protected function buildLines(array $lines, callable $defaultAccountId): array
    {
        // validated() can reorder keyed wildcard arrays; restore input order.
        ksort($lines);

        $calculator = app(DocumentTotalsCalculator::class);
        $taxRates = TaxRate::query()
            ->whereIn('id', array_filter(array_column($lines, 'tax_rate_id')))
            ->get()
            ->keyBy('id');

        $rows = [];

        foreach (array_values($lines) as $index => $line) {
            $taxRate = isset($line['tax_rate_id']) ? $taxRates->get((int) $line['tax_rate_id']) : null;
            $rate = $taxRate ? (string) $taxRate->rate : '0';
            $accountId = isset($line['account_id']) ? (int) $line['account_id'] : $defaultAccountId($line);

            if ($accountId === null) {
                throw ValidationException::withMessages([
                    "lines.{$index}.account_id" => 'No default account is configured; choose an account for this line.',
                ]);
            }

            $amounts = $calculator->line(
                (string) $line['quantity'],
                (string) $line['unit_price'],
                isset($line['discount_percent']) ? (string) $line['discount_percent'] : '0',
                $rate,
            );

            $rows[] = [
                'line_no' => $index + 1,
                'product_id' => $line['product_id'] ?? null,
                'description' => $line['description'],
                'quantity' => Money::normalize((string) $line['quantity']),
                'unit_price' => Money::normalize((string) $line['unit_price']),
                'discount_percent' => Money::normalize(isset($line['discount_percent']) ? (string) $line['discount_percent'] : '0'),
                'tax_rate_id' => $taxRate?->id,
                'tax_rate' => Money::normalize($rate),
                'account_id' => $accountId,
            ] + $amounts;
        }

        return $rows;
    }

    protected function accountIdByCode(string $code): ?int
    {
        return Account::query()->where('code', $code)->where('is_active', true)->value('id');
    }

    protected function refuseUnlessEditable(bool $editable, string $what): void
    {
        if (! $editable) {
            throw FinanceException::conflict("{$what} has been posted or voided and can no longer be changed.");
        }
    }
}
