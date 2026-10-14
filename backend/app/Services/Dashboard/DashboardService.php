<?php

namespace App\Services\Dashboard;

use App\Enums\Finance\AccountType;
use App\Enums\Finance\JournalEntryStatus;
use App\Models\Company;
use App\Models\User;
use App\Services\Approvals\ApprovalInbox;
use App\Support\Money\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Executive dashboard payload (frontend `DashboardPayload`).
 *
 * Rules:
 *  - a module's metrics/sections are present only when the user holds that
 *    module's permission — absent keys, never fabricated zeros;
 *  - present metrics with no data are "0.0000" / 0;
 *  - snapshot-only metrics (low stock, open orders, pending approvals) have
 *    `previous = null` because their history is not recorded;
 *  - revenue / expenses come from POSTED + REVERSED journal lines (the
 *    reversal's mirror nets them out), exactly like Finance\ReportService;
 *  - every query is constrained to the company explicitly and, when a branch
 *    scope applies, to the branch on records that carry one.
 */
class DashboardService
{
    private const POSTED_DOCUMENT_STATUSES = ['approved', 'partially_paid', 'paid'];

    private const OPEN_SALES_ORDER_STATUSES = ['confirmed', 'approved', 'partially_delivered'];

    private int $companyId;

    /** @var list<int>|null */
    private ?array $branchIds;

    public function __construct(private readonly ApprovalInbox $inbox) {}

    /**
     * Module keys included for this user.
     *
     * @return list<string>
     */
    public static function modulesFor(User $user): array
    {
        $modules = [];

        if ($user->hasPermission('reports.financial.view')) {
            $modules[] = 'finance';
        }
        if ($user->hasPermission('employees.view')) {
            $modules[] = 'hr';
        }
        if ($user->hasAnyPermission(['products.view', 'inventory.view'])) {
            $modules[] = 'inventory';
        }
        if ($user->hasAnyPermission(['sales_orders.view', 'invoices.view'])) {
            $modules[] = 'sales';
        }
        $modules[] = 'approvals';

        return $modules;
    }

    /**
     * @param  list<int>|null  $branchIds  null = all branches
     * @return array<string, mixed>
     */
    public function build(Company $company, User $user, string $from, string $to, ?array $branchIds): array
    {
        $this->companyId = $company->id;
        $this->branchIds = $branchIds;

        $previous = self::previousRange($from, $to);
        $modules = self::modulesFor($user);
        $has = fn (string $module) => in_array($module, $modules, true);

        $payload = [
            'period' => ['from' => $from, 'to' => $to, 'previous_from' => $previous['from'], 'previous_to' => $previous['to']],
            'currency' => $company->currency,
            'modules' => $modules,
            'metrics' => [],
            'trend' => [],
        ];

        if ($has('finance')) {
            $current = $this->glTotals($from, $to);
            $prior = $this->glTotals($previous['from'], $previous['to']);

            $payload['metrics']['revenue'] = $this->metric($current['revenue'], $prior['revenue']);
            $payload['metrics']['expenses'] = $this->metric($current['expenses'], $prior['expenses']);
            $payload['metrics']['net_profit'] = $this->metric(
                Money::sub($current['revenue'], $current['expenses']),
                Money::sub($prior['revenue'], $prior['expenses']),
            );
            $payload['metrics']['receivables'] = $this->metric($this->outstanding('invoices', $to), $this->outstanding('invoices', $previous['to']));
            $payload['metrics']['payables'] = $this->metric($this->outstanding('bills', $to), $this->outstanding('bills', $previous['to']));
            $payload['trend'] = $this->trend($from, $to);
            $payload['by_branch'] = $this->byBranch($from, $to);
        }

        if ($has('hr')) {
            $payload['metrics']['employees'] = $this->metric($this->headcount($to), $this->headcount($previous['to']));
            $payload['by_department'] = $this->byDepartment($to);
        }

        if ($has('inventory')) {
            $payload['metrics']['active_products'] = $this->metric($this->activeProducts($to), $this->activeProducts($previous['to']));
            $payload['metrics']['low_stock'] = $this->metric($this->lowStockQuery()->count(), null);
            $payload['low_stock_items'] = $this->lowStockItems();
        }

        if ($has('sales')) {
            if ($user->hasPermission('sales_orders.view')) {
                $payload['metrics']['open_sales_orders'] = $this->metric($this->openSalesOrders(), null);
            }
            if ($user->hasPermission('invoices.view')) {
                $payload['sales_by_month'] = $this->salesByMonth($from, $to);
                $payload['recent_invoices'] = $this->recentInvoices();
            }
        }

        $approvals = $this->inbox->pending($user);
        $payload['metrics']['pending_approvals'] = $this->metric($approvals->count(), null);
        $payload['pending_approvals'] = $approvals->take(5)->map(fn (array $item) => [
            'type' => $item['type'],
            'id' => $item['id'],
            'title' => $item['title'],
            'requested_by' => $item['requested_by'] ?? '',
            'url' => $item['url'],
            'created_at' => $item['created_at'],
        ])->values()->all();

        if ($user->hasPermission('audit.view')) {
            $payload['recent_activity'] = $this->recentActivity();
        }

        return $payload;
    }

    /**
     * Equal-length window immediately before [from, to].
     *
     * @return array{from: string, to: string}
     */
    public static function previousRange(string $from, string $to): array
    {
        $start = Carbon::parse($from);
        $days = (int) $start->diffInDays(Carbon::parse($to)) + 1;

        return [
            'from' => $start->copy()->subDays($days)->toDateString(),
            'to' => $start->copy()->subDay()->toDateString(),
        ];
    }

    /**
     * @return array{current: string|int|null, previous: string|int|null, change_percent: float|null}
     */
    public function metric(string|int|null $current, string|int|null $previous): array
    {
        $change = null;

        if ($current !== null && $previous !== null && ! Money::isZero((string) $previous)) {
            $delta = Money::sub((string) $current, (string) $previous);
            $change = (float) Money::round(Money::mul(Money::div($delta, Money::abs((string) $previous)), '100'), 2);
        }

        return ['current' => $current, 'previous' => $previous, 'change_percent' => $change];
    }

    // -- Finance -------------------------------------------------------------

    private function glLines(): Builder
    {
        return DB::table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->where('l.company_id', $this->companyId)
            ->where('e.company_id', $this->companyId)
            ->whereIn('e.status', [JournalEntryStatus::Posted->value, JournalEntryStatus::Reversed->value])
            ->whereIn('a.type', [AccountType::Revenue->value, AccountType::Expense->value])
            ->when($this->branchIds !== null, fn (Builder $q) => $q->whereIn(DB::raw('COALESCE(l.branch_id, e.branch_id)'), $this->branchIds ?: [0]));
    }

    private function glAmounts(Builder $query): Builder
    {
        return $query
            ->selectRaw('COALESCE(SUM(CASE WHEN a.type = ? THEN l.credit - l.debit ELSE 0 END), 0) as revenue', [AccountType::Revenue->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN a.type = ? THEN l.debit - l.credit ELSE 0 END), 0) as expenses', [AccountType::Expense->value]);
    }

    /**
     * @return array{revenue: string, expenses: string}
     */
    private function glTotals(string $from, string $to): array
    {
        $row = $this->glAmounts($this->glLines()->whereBetween('e.entry_date', [$from, $to]))->first();

        return [
            'revenue' => Money::normalize((string) ($row->revenue ?? '0')),
            'expenses' => Money::normalize((string) ($row->expenses ?? '0')),
        ];
    }

    /**
     * Daily buckets for ranges under 62 days, monthly otherwise; zero-filled.
     *
     * @return list<array{period: string, revenue: string, expenses: string}>
     */
    private function trend(string $from, string $to): array
    {
        $start = Carbon::parse($from);
        $end = Carbon::parse($to);
        $daily = $start->diffInDays($end) + 1 < 62;
        $format = $daily ? '%Y-%m-%d' : '%Y-%m';

        $rows = $this->glAmounts($this->glLines()->whereBetween('e.entry_date', [$from, $to]))
            ->selectRaw("DATE_FORMAT(e.entry_date, '{$format}') as bucket")
            ->groupBy('bucket')
            ->get()
            ->keyBy('bucket');

        $points = [];
        $cursor = $daily ? $start->copy() : $start->copy()->startOfMonth();

        while ($cursor->lte($end)) {
            $key = $daily ? $cursor->toDateString() : $cursor->format('Y-m');
            $row = $rows->get($key);
            $points[] = [
                'period' => $key,
                'revenue' => Money::normalize((string) ($row->revenue ?? '0')),
                'expenses' => Money::normalize((string) ($row->expenses ?? '0')),
            ];
            if ($daily) {
                $cursor->addDay();
            } else {
                $cursor->addMonthNoOverflow();
            }
        }

        return $points;
    }

    /**
     * @return list<array{branch_id: int|null, name: string, revenue: string, expenses: string}>
     */
    private function byBranch(string $from, string $to): array
    {
        return $this->glAmounts($this->glLines()->whereBetween('e.entry_date', [$from, $to]))
            ->leftJoin('branches as b', 'b.id', '=', DB::raw('COALESCE(l.branch_id, e.branch_id)'))
            ->selectRaw('b.id as branch_id, b.name as branch_name')
            ->groupBy('b.id', 'b.name')
            ->orderByRaw('b.name IS NULL, b.name')
            ->get()
            ->map(fn (object $row) => [
                'branch_id' => $row->branch_id === null ? null : (int) $row->branch_id,
                'name' => $row->branch_name ?? 'Unassigned',
                'revenue' => Money::normalize((string) $row->revenue),
                'expenses' => Money::normalize((string) $row->expenses),
            ])->values()->all();
    }

    /**
     * Outstanding AR (invoices) or AP (bills) as of a date: document total
     * minus posted allocations paid on/before the date (same rule as the
     * aging report), over approved / partially paid / paid documents.
     */
    private function outstanding(string $table, string $asOf): string
    {
        [$dateColumn, $allocType] = $table === 'invoices' ? ['invoice_date', 'invoice'] : ['bill_date', 'bill'];

        $documents = DB::table("{$table} as d")
            ->where('d.company_id', $this->companyId)
            ->whereIn('d.status', self::POSTED_DOCUMENT_STATUSES)
            ->where("d.{$dateColumn}", '<=', $asOf)
            ->when($this->branchIds !== null, fn (Builder $q) => $q->whereIn('d.branch_id', $this->branchIds ?: [0]))
            ->selectRaw(
                "d.total - COALESCE((
                    SELECT SUM(pa.amount) FROM payment_allocations pa
                    JOIN payments p ON p.id = pa.payment_id
                    WHERE pa.allocatable_type = ? AND pa.allocatable_id = d.id
                      AND p.status = 'posted' AND p.payment_date <= ?
                ), 0) as outstanding",
                [$allocType, $asOf],
            );

        $value = DB::query()->fromSub($documents, 'x')->where('outstanding', '>', 0)->sum('outstanding');

        return Money::normalize((string) $value);
    }

    // -- HR ------------------------------------------------------------------

    private function employedOn(string $date): Builder
    {
        return DB::table('employees as emp')
            ->where('emp.company_id', $this->companyId)
            ->whereNull('emp.deleted_at')
            ->where(fn (Builder $q) => $q->whereNull('emp.hired_at')->orWhere('emp.hired_at', '<=', $date))
            ->where(fn (Builder $q) => $q->whereNull('emp.terminated_at')->orWhere('emp.terminated_at', '>', $date))
            ->where(fn (Builder $q) => $q->where('emp.status', '!=', 'terminated')->orWhereNotNull('emp.terminated_at'))
            ->when($this->branchIds !== null, fn (Builder $q) => $q->whereIn('emp.branch_id', $this->branchIds ?: [0]));
    }

    private function headcount(string $date): int
    {
        return $this->employedOn($date)->count();
    }

    /**
     * @return list<array{department_id: int|null, name: string, headcount: int}>
     */
    private function byDepartment(string $date): array
    {
        return $this->employedOn($date)
            ->leftJoin('departments as d', 'd.id', '=', 'emp.department_id')
            ->groupBy('d.id', 'd.name')
            ->selectRaw('d.id as department_id, d.name, COUNT(*) as headcount')
            ->orderByDesc('headcount')->orderBy('d.name')
            ->get()
            ->map(fn (object $row) => [
                'department_id' => $row->department_id === null ? null : (int) $row->department_id,
                'name' => $row->name ?? 'Unassigned',
                'headcount' => (int) $row->headcount,
            ])->values()->all();
    }

    // -- Inventory -----------------------------------------------------------

    private function activeProducts(string $date): int
    {
        return DB::table('products')
            ->where('company_id', $this->companyId)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->where('created_at', '<=', Carbon::parse($date)->endOfDay())
            ->count();
    }

    private function lowStockQuery(): Builder
    {
        return DB::table('products as p')
            ->where('p.company_id', $this->companyId)
            ->whereNull('p.deleted_at')
            ->where('p.type', 'goods')
            ->where('p.is_active', true)
            ->where('p.reorder_level', '>', 0)
            ->whereRaw('COALESCE((SELECT SUM(sl.quantity_on_hand) FROM stock_levels sl WHERE sl.product_id = p.id AND sl.company_id = p.company_id), 0) <= p.reorder_level');
    }

    /**
     * @return list<array{id: int, sku: string, name: string, on_hand: string, reorder_level: string}>
     */
    private function lowStockItems(): array
    {
        return $this->lowStockQuery()
            ->selectRaw('p.id, p.sku, p.name, p.reorder_level, COALESCE((SELECT SUM(sl.quantity_on_hand) FROM stock_levels sl WHERE sl.product_id = p.id AND sl.company_id = p.company_id), 0) as on_hand')
            ->orderByRaw('on_hand - p.reorder_level')->orderBy('p.name')
            ->limit(5)
            ->get()
            ->map(fn (object $row) => [
                'id' => (int) $row->id,
                'sku' => (string) $row->sku,
                'name' => (string) $row->name,
                'on_hand' => Money::normalize((string) $row->on_hand),
                'reorder_level' => Money::normalize((string) $row->reorder_level),
            ])->values()->all();
    }

    // -- Sales ---------------------------------------------------------------

    private function openSalesOrders(): int
    {
        return DB::table('sales_orders')
            ->where('company_id', $this->companyId)
            ->whereNull('deleted_at')
            ->whereIn('status', self::OPEN_SALES_ORDER_STATUSES)
            ->count();
    }

    /**
     * @return list<array{period: string, total: string, count: int}>
     */
    private function salesByMonth(string $from, string $to): array
    {
        $rows = DB::table('invoices')
            ->where('company_id', $this->companyId)
            ->whereIn('status', self::POSTED_DOCUMENT_STATUSES)
            ->whereBetween('invoice_date', [$from, $to])
            ->when($this->branchIds !== null, fn (Builder $q) => $q->whereIn('branch_id', $this->branchIds ?: [0]))
            ->selectRaw("DATE_FORMAT(invoice_date, '%Y-%m') as period, COALESCE(SUM(total), 0) as total, COUNT(*) as n")
            ->groupBy('period')
            ->get()
            ->keyBy('period');

        $out = [];
        $cursor = Carbon::parse($from)->startOfMonth();
        $end = Carbon::parse($to);

        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m');
            $out[] = [
                'period' => $key,
                'total' => Money::normalize((string) ($rows->get($key)->total ?? '0')),
                'count' => (int) ($rows->get($key)->n ?? 0),
            ];
            $cursor->addMonthNoOverflow();
        }

        return $out;
    }

    /**
     * @return list<array{id: int, number: string, customer: string, total: string, status: string, date: string}>
     */
    private function recentInvoices(): array
    {
        return DB::table('invoices as i')
            ->leftJoin('customers as c', 'c.id', '=', 'i.customer_id')
            ->where('i.company_id', $this->companyId)
            ->when($this->branchIds !== null, fn (Builder $q) => $q->whereIn('i.branch_id', $this->branchIds ?: [0]))
            ->orderByDesc('i.invoice_date')->orderByDesc('i.id')
            ->limit(5)
            ->get(['i.id', 'i.number', 'c.name as customer', 'i.total', 'i.status', 'i.invoice_date'])
            ->map(fn (object $row) => [
                'id' => (int) $row->id,
                'number' => (string) $row->number,
                'customer' => (string) ($row->customer ?? ''),
                'total' => Money::normalize((string) $row->total),
                'status' => (string) $row->status,
                'date' => Carbon::parse($row->invoice_date)->toDateString(),
            ])->values()->all();
    }

    // -- Audit ---------------------------------------------------------------

    /**
     * @return list<array{id: int, action: string, entity_label: string|null, actor_name: string|null, created_at: string}>
     */
    private function recentActivity(): array
    {
        return DB::table('audit_logs')
            ->where('company_id', $this->companyId)
            ->orderByDesc('id')
            ->limit(8)
            ->get(['id', 'action', 'entity_label', 'actor_name', 'created_at'])
            ->map(fn (object $row) => [
                'id' => (int) $row->id,
                'action' => (string) $row->action,
                'entity_label' => $row->entity_label,
                'actor_name' => $row->actor_name,
                'created_at' => Carbon::parse($row->created_at, 'UTC')->toJSON(),
            ])->values()->all();
    }
}
