<?php

namespace App\Services\Approvals;

use App\Models\User;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Central approvals inbox: pending items across modules that the current
 * user may act on. Read-only — approve/reject stay on each module's endpoint.
 *
 * Every source is a bounded (LIMIT 200) query against the active tenant,
 * gated by the module's `x.approve` permission and excluding the user's own
 * requests (mirroring the module policies' "no self-approval" rules). Branch
 * restrictions on the membership are honoured where the record has a branch.
 *
 * @phpstan-type InboxItem array{type: string, id: int, number: ?string, title: string, requested_by: ?string, amount: ?string, currency: ?string, created_at: ?string, url: string}
 */
class ApprovalInbox
{
    public const PER_TYPE_LIMIT = 200;

    /** @var array<string, string> type => required permission */
    public const TYPES = [
        'leave_request' => 'leave.approve',
        'purchase_requisition' => 'requisitions.approve',
        'purchase_order' => 'purchase_orders.approve',
        'sales_order' => 'sales_orders.approve',
        'invoice' => 'invoices.approve',
        'bill' => 'bills.approve',
        'expense_claim' => 'expenses.approve',
        'payroll_run' => 'payroll.process',
    ];

    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * Types this user may approve (payroll only once its tables exist).
     *
     * @return list<string>
     */
    public function typesFor(User $user): array
    {
        return array_values(array_filter(
            array_keys(self::TYPES),
            fn (string $type) => $user->hasPermission(self::TYPES[$type])
                && ($type !== 'payroll_run' || Schema::hasTable('payroll_runs')),
        ));
    }

    /**
     * Pending items, newest first.
     *
     * @return Collection<int, InboxItem>
     */
    public function pending(User $user, ?array $types = null): Collection
    {
        $items = collect();

        foreach ($this->typesFor($user) as $type) {
            if ($types !== null && ! in_array($type, $types, true)) {
                continue;
            }

            $items = $items->concat($this->itemsFor($type, $user));
        }

        return $items
            ->sortBy([['created_at', 'desc'], ['type', 'asc'], ['id', 'desc']])
            ->values();
    }

    /**
     * Per-type counts plus total.
     *
     * @return array{total: int, by_type: array<string, int>}
     */
    public function counts(User $user): array
    {
        $byType = [];

        foreach ($this->typesFor($user) as $type) {
            $byType[$type] = $this->query($type, $user)->limit(self::PER_TYPE_LIMIT)->get(['x.id'])->count();
        }

        return ['total' => array_sum($byType), 'by_type' => $byType];
    }

    /**
     * @return Collection<int, InboxItem>
     */
    private function itemsFor(string $type, User $user): Collection
    {
        $currency = $this->tenant->company()?->currency;

        return $this->query($type, $user)
            ->orderByDesc('x.created_at')->orderByDesc('x.id')
            ->limit(self::PER_TYPE_LIMIT)
            ->get()
            ->map(fn (object $row) => $this->present($type, $row, $currency));
    }

    /**
     * The base query for one type, aliased `x`, selecting a uniform shape.
     */
    private function query(string $type, User $user): Builder
    {
        $companyId = (int) $this->tenant->id();
        $branches = $this->tenant->allowedBranchIds();
        $branchFilter = function (Builder $q, string $column) use ($branches): void {
            if ($branches !== null) {
                $q->where(fn (Builder $w) => $w->whereNull($column)->orWhereIn($column, $branches));
            }
        };

        $query = match ($type) {
            'leave_request' => DB::table('leave_requests as x')
                ->join('employees as e', 'e.id', '=', 'x.employee_id')
                ->leftJoin('leave_types as lt', 'lt.id', '=', 'x.leave_type_id')
                ->where('x.status', 'pending')
                ->where(fn (Builder $q) => $q->whereNull('e.user_id')->orWhere('e.user_id', '!=', $user->id))
                ->selectRaw("x.id, NULL as number, CONCAT(e.first_name, ' ', e.last_name) as requested_by, NULL as amount, NULL as currency, x.created_at, lt.name as extra, x.start_date as d1, x.end_date as d2, x.days as qty"),
            'purchase_requisition' => tap(DB::table('purchase_requisitions as x')
                ->leftJoin('users as u', 'u.id', '=', 'x.requester_id')
                ->where('x.status', 'submitted')
                ->where('x.requester_id', '!=', $user->id)
                ->selectRaw('x.id, x.number, u.name as requested_by, x.estimated_total as amount, NULL as currency, x.created_at, x.priority as extra, x.needed_by as d1, NULL as d2, NULL as qty'),
                fn (Builder $q) => $branchFilter($q, 'x.branch_id')),
            'purchase_order' => tap(DB::table('purchase_orders as x')
                ->leftJoin('users as u', 'u.id', '=', 'x.created_by')
                ->leftJoin('suppliers as s', 's.id', '=', 'x.supplier_id')
                ->where('x.status', 'submitted')
                ->where(fn (Builder $q) => $q->whereNull('x.created_by')->orWhere('x.created_by', '!=', $user->id))
                ->selectRaw('x.id, x.number, u.name as requested_by, x.total as amount, x.currency, x.created_at, s.name as extra, NULL as d1, NULL as d2, NULL as qty'),
                fn (Builder $q) => $branchFilter($q, 'x.branch_id')),
            'sales_order' => DB::table('sales_orders as x')
                ->leftJoin('users as u', 'u.id', '=', 'x.created_by')
                ->leftJoin('customers as c', 'c.id', '=', 'x.customer_id')
                ->where('x.status', 'confirmed')
                ->whereNull('x.deleted_at')
                ->where(fn (Builder $q) => $q->whereNull('x.created_by')->orWhere('x.created_by', '!=', $user->id))
                ->selectRaw('x.id, x.number, u.name as requested_by, x.total as amount, x.currency, x.created_at, c.name as extra, NULL as d1, NULL as d2, NULL as qty'),
            'invoice' => tap(DB::table('invoices as x')
                ->leftJoin('users as u', 'u.id', '=', 'x.created_by')
                ->leftJoin('customers as c', 'c.id', '=', 'x.customer_id')
                ->where('x.status', 'pending_approval')
                ->where(fn (Builder $q) => $q->whereNull('x.created_by')->orWhere('x.created_by', '!=', $user->id))
                ->selectRaw('x.id, x.number, u.name as requested_by, x.total as amount, x.currency, x.created_at, c.name as extra, NULL as d1, NULL as d2, NULL as qty'),
                fn (Builder $q) => $branchFilter($q, 'x.branch_id')),
            'bill' => tap(DB::table('bills as x')
                ->leftJoin('users as u', 'u.id', '=', 'x.created_by')
                ->leftJoin('suppliers as s', 's.id', '=', 'x.supplier_id')
                ->where('x.status', 'pending_approval')
                ->where(fn (Builder $q) => $q->whereNull('x.created_by')->orWhere('x.created_by', '!=', $user->id))
                ->selectRaw('x.id, x.number, u.name as requested_by, x.total as amount, x.currency, x.created_at, s.name as extra, NULL as d1, NULL as d2, NULL as qty'),
                fn (Builder $q) => $branchFilter($q, 'x.branch_id')),
            'expense_claim' => DB::table('expense_claims as x')
                ->leftJoin('users as u', 'u.id', '=', 'x.user_id')
                ->where('x.status', 'submitted')
                ->where('x.user_id', '!=', $user->id)
                ->selectRaw('x.id, x.number, u.name as requested_by, x.total as amount, x.currency, x.created_at, NULL as extra, x.claim_date as d1, NULL as d2, NULL as qty'),
            'payroll_run' => DB::table('payroll_runs as x')
                ->leftJoin('users as u', 'u.id', '=', 'x.created_by')
                ->where('x.status', 'calculated')
                ->where(fn (Builder $q) => $q->whereNull('x.created_by')->orWhere('x.created_by', '!=', $user->id))
                ->selectRaw('x.id, x.number, u.name as requested_by, x.total_net as amount, x.currency, x.created_at, x.name as extra, x.period_start as d1, x.period_end as d2, x.employee_count as qty'),
        };

        return $query->where('x.company_id', $companyId);
    }

    /**
     * @return InboxItem
     */
    private function present(string $type, object $row, ?string $companyCurrency): array
    {
        $number = $row->number === null ? null : (string) $row->number;
        $who = $row->requested_by === null ? null : (string) $row->requested_by;

        $title = match ($type) {
            'leave_request' => sprintf('Leave request — %s (%s, %s day%s, %s to %s)',
                $who ?? 'Employee', $row->extra ?? 'Leave', rtrim(rtrim((string) $row->qty, '0'), '.'),
                (string) $row->qty === '1.0' ? '' : 's', $this->date($row->d1), $this->date($row->d2)),
            'purchase_requisition' => "Purchase requisition {$number}".($row->d1 ? ' — needed by '.$this->date($row->d1) : ''),
            'purchase_order' => "Purchase order {$number}".($row->extra ? " — {$row->extra}" : ''),
            'sales_order' => "Sales order {$number}".($row->extra ? " — {$row->extra}" : ''),
            'invoice' => "Invoice {$number}".($row->extra ? " — {$row->extra}" : ''),
            'bill' => "Bill {$number}".($row->extra ? " — {$row->extra}" : ''),
            'expense_claim' => "Expense claim {$number}".($who ? " — {$who}" : ''),
            'payroll_run' => "Payroll run {$number}".($row->extra ? " — {$row->extra}" : ''),
        };

        $url = match ($type) {
            'leave_request' => '/hr/leave?tab=approvals&request='.$row->id,
            'purchase_requisition' => '/procurement/requisitions/'.$row->id,
            'purchase_order' => '/procurement/orders/'.$row->id,
            'sales_order' => '/sales/orders/'.$row->id,
            'invoice' => '/finance/invoices/'.$row->id,
            'bill' => '/finance/bills/'.$row->id,
            'expense_claim' => '/finance/expenses/'.$row->id,
            'payroll_run' => '/payroll/runs/'.$row->id,
        };

        $amount = $row->amount === null ? null : Money::normalize((string) $row->amount);

        return [
            'type' => $type,
            'id' => (int) $row->id,
            'number' => $number,
            'title' => $title,
            'requested_by' => $who,
            'amount' => $amount,
            'currency' => $amount === null ? null : ($row->currency ?? $companyCurrency),
            'created_at' => $row->created_at === null ? null : Carbon::parse($row->created_at, 'UTC')->toJSON(),
            'url' => $url,
        ];
    }

    private function date(mixed $value): string
    {
        return $value === null ? '?' : Carbon::parse((string) $value)->toDateString();
    }
}
