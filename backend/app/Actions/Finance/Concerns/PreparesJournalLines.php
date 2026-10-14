<?php

namespace App\Actions\Finance\Concerns;

use App\Models\Finance\Account;
use App\Models\Finance\FinancialPeriod;
use App\Models\Finance\TaxRate;
use App\Support\Money\Money;
use Illuminate\Validation\ValidationException;

/**
 * Normalises raw line input (from a request or a sub-ledger) into rows ready
 * for insertion: resolves account_code → account_id inside the company,
 * rejects inactive/foreign accounts, exact-decimal amounts, one side per line.
 */
trait PreparesJournalLines
{
    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{lines: list<array<string, mixed>>, total_debit: string, total_credit: string}
     */
    protected function prepareLines(int $companyId, array $lines): array
    {
        // FormRequest::validated() can reorder keyed wildcard arrays; restore input order.
        ksort($lines);

        if (count($lines) < 2) {
            throw ValidationException::withMessages(['lines' => 'A journal entry needs at least two lines.']);
        }

        $accounts = $this->resolveAccounts($companyId, $lines);
        $taxRateIds = TaxRate::forCompany($companyId)->pluck('id')->all();

        $prepared = [];
        $debits = [];
        $credits = [];

        foreach (array_values($lines) as $index => $line) {
            $account = $accounts[$index] ?? null;

            if ($account === null) {
                throw ValidationException::withMessages(["lines.{$index}.account_id" => 'The selected account is invalid.']);
            }

            if (! $account->is_active) {
                throw ValidationException::withMessages(["lines.{$index}.account_id" => "Account {$account->code} is inactive."]);
            }

            try {
                $debit = Money::normalize($line['debit'] ?? null);
                $credit = Money::normalize($line['credit'] ?? null);
            } catch (\InvalidArgumentException) {
                throw ValidationException::withMessages(["lines.{$index}.debit" => 'Amounts must be decimal numbers.']);
            }

            if (Money::isNegative($debit) || Money::isNegative($credit)) {
                throw ValidationException::withMessages(["lines.{$index}.debit" => 'Amounts cannot be negative.']);
            }

            $hasDebit = ! Money::isZero($debit);
            $hasCredit = ! Money::isZero($credit);

            if ($hasDebit === $hasCredit) {
                throw ValidationException::withMessages([
                    "lines.{$index}.debit" => 'Each line must have either a debit or a credit amount, not both or neither.',
                ]);
            }

            if (isset($line['tax_rate_id']) && ! in_array((int) $line['tax_rate_id'], $taxRateIds, true)) {
                throw ValidationException::withMessages(["lines.{$index}.tax_rate_id" => 'The selected tax rate is invalid.']);
            }

            $debits[] = $debit;
            $credits[] = $credit;

            $prepared[] = [
                'company_id' => $companyId,
                'line_no' => $index + 1,
                'account_id' => $account->id,
                'branch_id' => isset($line['branch_id']) ? (int) $line['branch_id'] : null,
                'partner_type' => $line['partner_type'] ?? null,
                'partner_id' => isset($line['partner_id']) ? (int) $line['partner_id'] : null,
                'tax_rate_id' => isset($line['tax_rate_id']) ? (int) $line['tax_rate_id'] : null,
                'description' => $line['description'] ?? null,
                'debit' => $debit,
                'credit' => $credit,
            ];
        }

        return [
            'lines' => $prepared,
            'total_debit' => Money::sum($debits),
            'total_credit' => Money::sum($credits),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, Account|null> indexed like $lines
     */
    private function resolveAccounts(int $companyId, array $lines): array
    {
        $ids = [];
        $codes = [];

        foreach ($lines as $line) {
            if (isset($line['account_id'])) {
                $ids[] = (int) $line['account_id'];
            } elseif (isset($line['account_code'])) {
                $codes[] = (string) $line['account_code'];
            }
        }

        $found = Account::forCompany($companyId)
            ->where(fn ($q) => $q->whereIn('id', $ids ?: [0])->orWhereIn('code', $codes ?: ['']))
            ->get();

        $byId = $found->keyBy('id');
        $byCode = $found->keyBy('code');

        $resolved = [];

        foreach (array_values($lines) as $index => $line) {
            $resolved[$index] = match (true) {
                isset($line['account_id']) => $byId->get((int) $line['account_id']),
                isset($line['account_code']) => $byCode->get((string) $line['account_code']),
                default => null,
            };
        }

        return $resolved;
    }

    protected function resolvePeriodId(int $companyId, string $date): ?int
    {
        return FinancialPeriod::covering($companyId, $date)?->id;
    }
}
