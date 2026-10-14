<?php

namespace App\Services\Sales;

use App\Enums\Finance\PaymentPartnerType;
use App\Enums\Sales\OpportunityStatus;
use App\Enums\Sales\QuotationStatus;
use App\Enums\Sales\SalesOrderStatus;
use App\Models\Sales\Customer;
use App\Models\Sales\Opportunity;
use App\Models\Sales\PipelineStage;
use App\Models\Sales\Quotation;
use App\Models\Sales\SalesOrder;
use App\Models\User;
use App\Services\Finance\Receivables\ReceivablesReportService;
use App\Support\Money\Money;
use Carbon\CarbonInterface;

/**
 * Aggregate-SQL reports for the sales module (tenant scope applies through
 * the models' global scope; no per-row PHP loops over documents).
 */
final class SalesReports
{
    /**
     * Pipeline by stage with weighted value (Σ value × probability / 100) and
     * the win rate over the last 90 days.
     *
     * @return array<string, mixed>
     */
    public function pipelineSummary(): array
    {
        $stages = PipelineStage::query()->orderBy('position')->orderBy('id')->get();

        $byStage = Opportunity::query()
            ->groupBy('stage_id')
            ->selectRaw('stage_id, COUNT(*) AS cnt, COALESCE(SUM(value), 0) AS total_value, COALESCE(SUM(value * probability / 100), 0) AS weighted_value')
            ->toBase()->get()
            ->keyBy('stage_id');

        $open = Opportunity::query()
            ->where('status', OpportunityStatus::Open)
            ->selectRaw('COUNT(*) AS cnt, COALESCE(SUM(value), 0) AS total_value, COALESCE(SUM(value * probability / 100), 0) AS weighted_value')
            ->toBase()->first();

        $closed = Opportunity::query()
            ->whereIn('status', [OpportunityStatus::Won, OpportunityStatus::Lost])
            ->where('closed_at', '>=', now()->subDays(90))
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS won, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS lost', [
                OpportunityStatus::Won->value, OpportunityStatus::Lost->value,
            ])
            ->toBase()->first();

        $won = (int) ($closed?->won ?? 0);
        $lost = (int) ($closed?->lost ?? 0);

        return [
            'stages' => $stages->map(function (PipelineStage $stage) use ($byStage) {
                $row = $byStage->get($stage->id);

                return [
                    'stage' => ['id' => $stage->id, 'name' => $stage->name, 'color' => $stage->color, 'probability' => $stage->probability, 'is_won' => $stage->is_won, 'is_lost' => $stage->is_lost],
                    'count' => (int) ($row?->cnt ?? 0),
                    'total_value' => Money::normalize($row?->total_value ?? 0),
                    'weighted_value' => Money::normalize($row?->weighted_value ?? 0),
                ];
            })->values()->all(),
            'open' => [
                'count' => (int) ($open?->cnt ?? 0),
                'total_value' => Money::normalize($open?->total_value ?? 0),
                'weighted_value' => Money::normalize($open?->weighted_value ?? 0),
            ],
            'win_rate_90d' => [
                'won' => $won,
                'lost' => $lost,
                'rate' => ($won + $lost) > 0 ? Money::round(bcdiv(bcmul((string) $won, '100', 4), (string) ($won + $lost), 4), 2) : null,
            ],
        ];
    }

    /**
     * Quotation and order volumes grouped by sales rep, customer or month,
     * with the quotation → order conversion rate per group.
     *
     * @return array<string, mixed>
     */
    public function performance(CarbonInterface $from, CarbonInterface $to, string $groupBy): array
    {
        [$quoteKey, $orderKey] = match ($groupBy) {
            'rep' => ['quotations.sales_rep_id', 'sales_orders.sales_rep_id'],
            'customer' => ['quotations.customer_id', 'sales_orders.customer_id'],
            default => ["DATE_FORMAT(quotations.date, '%Y-%m')", "DATE_FORMAT(sales_orders.order_date, '%Y-%m')"],
        };

        $quotes = Quotation::query()
            ->whereBetween('quotations.date', [$from->toDateString(), $to->toDateString()])
            ->groupByRaw($quoteKey)
            ->selectRaw("{$quoteKey} AS grp, COUNT(*) AS cnt, COALESCE(SUM(total), 0) AS total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS converted", [QuotationStatus::Converted->value])
            ->toBase()->get()
            ->keyBy(fn ($row) => (string) ($row->grp ?? ''));

        $orders = SalesOrder::query()
            ->whereBetween('sales_orders.order_date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('sales_orders.status', array_map(fn (SalesOrderStatus $s) => $s->value, SalesOrderStatus::approvedOrLater()))
            ->groupByRaw($orderKey)
            ->selectRaw("{$orderKey} AS grp, COUNT(*) AS cnt, COALESCE(SUM(total), 0) AS total")
            ->toBase()->get()
            ->keyBy(fn ($row) => (string) ($row->grp ?? ''));

        $keys = $quotes->keys()->merge($orders->keys())->unique()->values();
        $labels = $this->labelsFor($groupBy, $keys->all());

        $rows = $keys->map(function (string $key) use ($quotes, $orders, $labels, $groupBy) {
            $q = $quotes->get($key);
            $o = $orders->get($key);
            $quoteCount = (int) ($q?->cnt ?? 0);
            $converted = (int) ($q?->converted ?? 0);

            return [
                'key' => $key === '' ? null : ($groupBy === 'month' ? $key : (int) $key),
                $groupBy => $labels[$key] ?? null,
                'quotations' => ['count' => $quoteCount, 'total' => Money::normalize($q?->total ?? 0), 'converted' => $converted],
                'orders' => ['count' => (int) ($o?->cnt ?? 0), 'total' => Money::normalize($o?->total ?? 0)],
                'conversion_rate' => $quoteCount > 0 ? Money::round(bcdiv(bcmul((string) $converted, '100', 4), (string) $quoteCount, 4), 2) : null,
            ];
        })->sortBy(fn (array $row) => $groupBy === 'month' ? (string) $row['key'] : (string) ($row[$groupBy]['name'] ?? 'zzz'))->values()->all();

        $quoteCount = (int) $quotes->sum('cnt');
        $converted = (int) $quotes->sum('converted');

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'group_by' => $groupBy,
            'rows' => $rows,
            'totals' => [
                'quotations' => ['count' => $quoteCount, 'total' => Money::sum($quotes->map(fn ($r) => Money::normalize($r->total))->all()), 'converted' => $converted],
                'orders' => ['count' => (int) $orders->sum('cnt'), 'total' => Money::sum($orders->map(fn ($r) => Money::normalize($r->total))->all())],
                'conversion_rate' => $quoteCount > 0 ? Money::round(bcdiv(bcmul((string) $converted, '100', 4), (string) $quoteCount, 4), 2) : null,
            ],
        ];
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    private function labelsFor(string $groupBy, array $keys): array
    {
        $ids = array_values(array_filter(array_map('intval', $keys)));

        return match ($groupBy) {
            'rep' => User::query()->whereKey($ids)->get(['id', 'name'])
                ->mapWithKeys(fn (User $u) => [(string) $u->id => ['id' => $u->id, 'name' => $u->name]])->all(),
            'customer' => Customer::query()->whereKey($ids)->get(['id', 'name', 'code'])
                ->mapWithKeys(fn (Customer $c) => [(string) $c->id => ['id' => $c->id, 'name' => $c->name, 'code' => $c->code]])->all(),
            default => collect($keys)->mapWithKeys(fn (string $k) => [$k => $k])->all(),
        };
    }

    /**
     * Per-customer counts & totals of quotations and orders by status.
     *
     * @return array<string, mixed>
     */
    public function customerSummary(Customer $customer): array
    {
        $group = fn ($rows) => [
            'count' => (int) $rows->sum('cnt'),
            'total' => Money::sum($rows->map(fn ($r) => Money::normalize($r->total))->all()),
            'by_status' => $rows->mapWithKeys(fn ($r) => [$r->status => ['count' => (int) $r->cnt, 'total' => Money::normalize($r->total)]])->all(),
        ];

        $quotes = Quotation::query()->where('customer_id', $customer->id)
            ->groupBy('status')->selectRaw('status, COUNT(*) AS cnt, COALESCE(SUM(total), 0) AS total')->toBase()->get();
        $orders = SalesOrder::query()->where('customer_id', $customer->id)
            ->groupBy('status')->selectRaw('status, COUNT(*) AS cnt, COALESCE(SUM(total), 0) AS total')->toBase()->get();
        $opps = Opportunity::query()->where('customer_id', $customer->id)
            ->groupBy('status')->selectRaw('status, COUNT(*) AS cnt, COALESCE(SUM(value), 0) AS total')->toBase()->get();

        $balance = app(ReceivablesReportService::class)->partnerBalance(
            (int) $customer->company_id, PaymentPartnerType::Customer, (int) $customer->id, now()->toDateString(),
        );

        return [
            'customer_id' => $customer->id,
            'quotations' => $group($quotes),
            'sales_orders' => $group($orders),
            'opportunities' => $group($opps),
            'last_order_date' => SalesOrder::query()->where('customer_id', $customer->id)->max('order_date'),
            'outstanding_balance' => $balance['outstanding'],
            'overdue_balance' => $balance['overdue'],
            'open_invoices_count' => $balance['open_count'],
            'overdue_invoices_count' => $balance['overdue_count'],
        ];
    }
}
