<?php

namespace App\Services\Finance\Receivables;

use App\Support\Money\Money;

/**
 * Accumulates debit/credit amounts per (account, side, partner, tax rate) and
 * emits LedgerPoster line arrays. Lines of zero are dropped (the ledger
 * refuses them), and accounts can be given by id or by chart code.
 *
 *   (new JournalLinesBuilder)
 *       ->debit(['account_code' => '1100'], '207.00', ['partner_type' => 'customer', 'partner_id' => 9])
 *       ->credit(['account_id' => 12], '180.00')
 *       ->credit(['account_code' => '2100'], '27.00', ['tax_rate_id' => 3])
 *       ->lines();
 */
final class JournalLinesBuilder
{
    /** @var array<string, array<string, mixed>> */
    private array $lines = [];

    /**
     * @param  array{account_id?: int, account_code?: string}  $account
     * @param  array<string, mixed>  $extra
     */
    public function debit(array $account, string $amount, array $extra = []): static
    {
        return $this->add('debit', $account, $amount, $extra);
    }

    /**
     * @param  array{account_id?: int, account_code?: string}  $account
     * @param  array<string, mixed>  $extra
     */
    public function credit(array $account, string $amount, array $extra = []): static
    {
        return $this->add('credit', $account, $amount, $extra);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function lines(): array
    {
        return array_values(array_filter(
            $this->lines,
            fn (array $line) => ! Money::isZero($line['debit']) || ! Money::isZero($line['credit']),
        ));
    }

    /**
     * @param  array{account_id?: int, account_code?: string}  $account
     * @param  array<string, mixed>  $extra
     */
    private function add(string $side, array $account, string $amount, array $extra): static
    {
        $key = $side.'|'.json_encode($account).'|'.json_encode(array_intersect_key($extra, array_flip(['partner_type', 'partner_id', 'tax_rate_id'])));

        if (! isset($this->lines[$key])) {
            $this->lines[$key] = $account + $extra + ['debit' => Money::zero(), 'credit' => Money::zero()];
        }

        $this->lines[$key][$side] = Money::add($this->lines[$key][$side], Money::normalize($amount));

        return $this;
    }
}
