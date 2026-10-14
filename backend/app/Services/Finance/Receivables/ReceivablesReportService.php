<?php

namespace App\Services\Finance\Receivables;

use App\Enums\Finance\PaymentPartnerType;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;

/**
 * AR/AP sub-ledger reports: aging, dashboard summary and customer statement.
 *
 * Aging reconstructs each document's outstanding amount *as of* the date
 * (total − posted allocations with payment_date ≤ as_of), then buckets it by
 * days past due with one SQL CASE aggregation per partner. Voided documents
 * are excluded entirely.
 */
class ReceivablesReportService
{
    /** @var array<string, string> */
    public const BUCKETS = [
        'current' => 'DATEDIFF(?, due_date) <= 0',
        'days_1_30' => 'DATEDIFF(?, due_date) BETWEEN 1 AND 30',
        'days_31_60' => 'DATEDIFF(?, due_date) BETWEEN 31 AND 60',
        'days_61_90' => 'DATEDIFF(?, due_date) BETWEEN 61 AND 90',
        'over_90' => 'DATEDIFF(?, due_date) > 90',
    ];

    private const POSTED_STATUSES = ['approved', 'partially_paid', 'paid'];

    private const OPEN_STATUSES = ['approved', 'partially_paid'];

    public function __construct(private readonly PartnerDirectory $partners) {}

    /**
     * @return array{as_of: string, rows: list<array<string, mixed>>, totals: array<string, string|int>}
     */
    public function aging(int $companyId, PaymentPartnerType $partnerType, string $asOf): array
    {
        [$table, $partnerColumn, $dateColumn, $allocType] = match ($partnerType) {
            PaymentPartnerType::Customer => ['invoices', 'customer_id', 'invoice_date', 'invoice'],
            default => ['bills', 'supplier_id', 'bill_date', 'bill'],
        };

        $documents = DB::table("{$table} as d")
            ->where('d.company_id', $companyId)
            ->whereIn('d.status', self::POSTED_STATUSES)
            ->whereDate("d.{$dateColumn}", '<=', $asOf)
            ->selectRaw(
                "d.{$partnerColumn} as partner_id, d.due_date, d.total - COALESCE((
                    SELECT SUM(pa.amount) FROM payment_allocations pa
                    JOIN payments p ON p.id = pa.payment_id
                    WHERE pa.allocatable_type = ? AND pa.allocatable_id = d.id
                      AND p.status = 'posted' AND p.payment_date <= ?
                ), 0) as outstanding",
                [$allocType, $asOf],
            );

        $select = ['partner_id', 'COUNT(*) as documents', 'SUM(outstanding) as total'];
        $bindings = [];

        foreach (self::BUCKETS as $bucket => $condition) {
            $select[] = "SUM(CASE WHEN {$condition} THEN outstanding ELSE 0 END) as {$bucket}";
            $bindings[] = $asOf;
        }

        $rows = DB::query()
            ->fromSub($documents, 'x')
            ->where('outstanding', '>', 0)
            ->groupBy('partner_id')
            ->selectRaw(implode(', ', $select), $bindings)
            ->orderByDesc('total')
            ->get();

        $names = $this->partners->names($partnerType, $rows->pluck('partner_id')->map(fn ($id) => (int) $id)->all(), $companyId);
        $keys = [...array_keys(self::BUCKETS), 'total'];
        $totals = array_fill_keys($keys, Money::zero()) + ['documents' => 0];

        $out = $rows->map(function (object $row) use ($names, $keys, &$totals): array {
            $line = ['partner_id' => (int) $row->partner_id, 'partner_name' => $names[(int) $row->partner_id] ?? null, 'documents' => (int) $row->documents];

            foreach ($keys as $key) {
                $line[$key] = Money::normalize((string) $row->{$key});
                $totals[$key] = Money::add($totals[$key], $line[$key]);
            }

            $totals['documents'] += $line['documents'];

            return $line;
        })->values()->all();

        return ['as_of' => $asOf, 'rows' => $out, 'totals' => $totals];
    }

    /**
     * Open balance of one customer (invoices) or supplier (bills): the sum of
     * balance_due over approved / partially paid documents, plus the overdue
     * part (due date before $today). Amounts are in document currency.
     *
     * @return array{outstanding: string, overdue: string, open_count: int, overdue_count: int}
     */
    public function partnerBalance(int $companyId, PaymentPartnerType $partnerType, int $partnerId, string $today): array
    {
        [$table, $column] = $partnerType === PaymentPartnerType::Supplier ? ['bills', 'supplier_id'] : ['invoices', 'customer_id'];

        $row = DB::table($table)
            ->where('company_id', $companyId)
            ->where($column, $partnerId)
            ->whereIn('status', self::OPEN_STATUSES)
            ->selectRaw('COUNT(*) AS n, COALESCE(SUM(balance_due), 0) AS amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN due_date < ? THEN 1 ELSE 0 END), 0) AS overdue_n', [$today])
            ->selectRaw('COALESCE(SUM(CASE WHEN due_date < ? THEN balance_due ELSE 0 END), 0) AS overdue_amount', [$today])
            ->first();

        return [
            'outstanding' => Money::normalize((string) ($row->amount ?? '0')),
            'overdue' => Money::normalize((string) ($row->overdue_amount ?? '0')),
            'open_count' => (int) ($row->n ?? 0),
            'overdue_count' => (int) ($row->overdue_n ?? 0),
        ];
    }

    /**
     * Dashboard KPIs.
     *
     * @return array<string, mixed>
     */
    public function summary(int $companyId, string $today): array
    {
        $open = fn (string $table) => DB::table($table)->where('company_id', $companyId)->whereIn('status', self::OPEN_STATUSES);

        $ar = $open('invoices')->selectRaw('COUNT(*) as n, COALESCE(SUM(balance_due), 0) as amount')->first();
        $arOverdue = $open('invoices')->whereDate('due_date', '<', $today)->selectRaw('COUNT(*) as n, COALESCE(SUM(balance_due), 0) as amount')->first();
        $ap = $open('bills')->selectRaw('COUNT(*) as n, COALESCE(SUM(balance_due), 0) as amount')->first();
        $apOverdue = $open('bills')->whereDate('due_date', '<', $today)->selectRaw('COUNT(*) as n, COALESCE(SUM(balance_due), 0) as amount')->first();
        $claims = DB::table('expense_claims')->where('company_id', $companyId)->where('status', 'approved')
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(balance_due), 0) as amount')->first();

        $paidAfter = DB::table('invoices as i')
            ->join('payment_allocations as pa', fn ($join) => $join->on('pa.allocatable_id', '=', 'i.id')->where('pa.allocatable_type', 'invoice'))
            ->join('payments as p', fn ($join) => $join->on('p.id', '=', 'pa.payment_id')->where('p.status', 'posted'))
            ->where('i.company_id', $companyId)
            ->where('i.status', 'paid')
            ->groupBy('i.id', 'i.invoice_date')
            ->selectRaw('DATEDIFF(MAX(p.payment_date), i.invoice_date) as days');
        $avgDays = DB::query()->fromSub($paidAfter, 't')->avg('days');

        $sales90 = (string) DB::table('invoices')->where('company_id', $companyId)->whereIn('status', self::POSTED_STATUSES)
            ->whereDate('invoice_date', '>', now()->parse($today)->subDays(90)->toDateString())
            ->whereDate('invoice_date', '<=', $today)
            ->sum('total');

        $arAmount = Money::normalize((string) $ar->amount);
        $dso = Money::compare(Money::normalize($sales90), '0') > 0
            ? Money::round(Money::mul(Money::div($arAmount, Money::normalize($sales90)), '90'), 1)
            : null;

        return [
            'as_of' => $today,
            'receivables' => [
                'outstanding' => $arAmount,
                'open_count' => (int) $ar->n,
                'overdue_amount' => Money::normalize((string) $arOverdue->amount),
                'overdue_count' => (int) $arOverdue->n,
            ],
            'payables' => [
                'outstanding' => Money::normalize((string) $ap->amount),
                'open_count' => (int) $ap->n,
                'overdue_amount' => Money::normalize((string) $apOverdue->amount),
                'overdue_count' => (int) $apOverdue->n,
            ],
            'expense_claims_payable' => [
                'outstanding' => Money::normalize((string) $claims->amount),
                'count' => (int) $claims->n,
            ],
            'average_days_to_pay' => $avgDays === null ? null : Money::round(Money::normalize((string) $avgDays), 1),
            'dso_days' => $dso,
        ];
    }

    /**
     * Customer statement: posted invoices (debits) and posted receipts
     * (credits) with an opening balance and running balance.
     *
     * @return array<string, mixed>
     */
    public function customerStatement(int $companyId, int $customerId, string $from, string $to): array
    {
        $invoices = DB::table('invoices')->where('company_id', $companyId)->where('customer_id', $customerId)
            ->whereIn('status', self::POSTED_STATUSES);
        $receipts = DB::table('payments')->where('company_id', $companyId)->where('partner_type', 'customer')
            ->where('partner_id', $customerId)->where('status', 'posted');

        $opening = Money::sub(
            Money::normalize((string) (clone $invoices)->whereDate('invoice_date', '<', $from)->sum('total')),
            Money::normalize((string) (clone $receipts)->whereDate('payment_date', '<', $from)->sum('amount')),
        );

        $entries = collect()
            ->concat((clone $invoices)->whereBetween('invoice_date', [$from, $to])->get(['id', 'number', 'invoice_date', 'due_date', 'total'])
                ->map(fn (object $i) => ['date' => (string) $i->invoice_date, 'type' => 'invoice', 'id' => (int) $i->id, 'number' => $i->number, 'due_date' => (string) $i->due_date, 'debit' => Money::normalize((string) $i->total), 'credit' => Money::zero()]))
            ->concat((clone $receipts)->whereBetween('payment_date', [$from, $to])->get(['id', 'number', 'payment_date', 'reference', 'amount'])
                ->map(fn (object $p) => ['date' => (string) $p->payment_date, 'type' => 'receipt', 'id' => (int) $p->id, 'number' => $p->number, 'reference' => $p->reference, 'debit' => Money::zero(), 'credit' => Money::normalize((string) $p->amount)]))
            ->sortBy([['date', 'asc'], ['type', 'asc'], ['id', 'asc']])
            ->values();

        $balance = $opening;
        $debits = Money::zero();
        $credits = Money::zero();

        $lines = $entries->map(function (array $entry) use (&$balance, &$debits, &$credits): array {
            $balance = Money::sub(Money::add($balance, $entry['debit']), $entry['credit']);
            $debits = Money::add($debits, $entry['debit']);
            $credits = Money::add($credits, $entry['credit']);

            return $entry + ['balance' => $balance];
        })->all();

        $customer = $this->partners->details('customers', $customerId, $companyId);

        return [
            'customer' => $customer === null ? ['id' => $customerId] : [
                'id' => $customerId,
                'code' => $customer['code'] ?? null,
                'name' => $customer['name'] ?? null,
                'email' => $customer['email'] ?? null,
                'currency' => $customer['currency'] ?? null,
            ],
            'from' => $from,
            'to' => $to,
            'opening_balance' => $opening,
            'entries' => $lines,
            'total_debits' => $debits,
            'total_credits' => $credits,
            'closing_balance' => $balance,
        ];
    }
}
