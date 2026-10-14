<?php

namespace App\Services\Search;

use App\Models\Documents\Document;
use App\Models\Finance\Account;
use App\Models\Finance\Invoice;
use App\Models\HR\Employee;
use App\Models\Inventory\Product;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\Supplier;
use App\Models\Projects\Project;
use App\Models\Sales\Customer;
use App\Models\Sales\Quotation;
use App\Models\Sales\SalesOrder;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Global search across modules. Each type is gated by its module's view
 * permission and runs one simple, tenant-scoped (BelongsToCompany) LIKE query
 * with escaped wildcards and LIMIT 5 — no joins beyond a single eager load.
 * Prefix matches on codes/numbers rank first.
 *
 * @phpstan-type SearchHit array{type: string, id: int, title: string, subtitle: ?string, url: string}
 */
class GlobalSearch
{
    public const PER_TYPE = 5;

    /** @var array<string, list<string>> type => any-of permissions */
    public const TYPES = [
        'employees' => ['employees.view'],
        'customers' => ['customers.view'],
        'suppliers' => ['suppliers.view'],
        'products' => ['products.view', 'inventory.view'],
        'invoices' => ['invoices.view'],
        'purchase_orders' => ['purchase_orders.view'],
        'sales_orders' => ['sales_orders.view'],
        'quotations' => ['quotations.view'],
        'projects' => ['projects.view'],
        'documents' => ['documents.view'],
        'accounts' => ['accounts.view'],
    ];

    /**
     * @return list<string>
     */
    public function typesFor(User $user): array
    {
        return array_values(array_filter(array_keys(self::TYPES), fn (string $type) => $user->hasAnyPermission(self::TYPES[$type])));
    }

    /**
     * @param  list<string>|null  $types
     * @return Collection<int, SearchHit>
     */
    public function search(User $user, string $term, ?array $types = null): Collection
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
        $contains = '%'.$escaped.'%';
        $prefix = $escaped.'%';
        $results = collect();

        foreach ($this->typesFor($user) as $type) {
            if ($types !== null && ! in_array($type, $types, true)) {
                continue;
            }

            $results = $results->concat($this->searchType($type, $user, $contains, $prefix));
        }

        return $results->values();
    }

    /**
     * @return Collection<int, SearchHit>
     */
    private function searchType(string $type, User $user, string $contains, string $prefix): Collection
    {
        return match ($type) {
            'employees' => $this->run(
                Employee::query(), ['employee_code', 'first_name', 'last_name', 'email'], 'employee_code', $contains, $prefix,
                fn ($q) => $q->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$contains]),
            )->map(fn (Employee $e) => $this->hit('employee', $e->id, trim($e->first_name.' '.$e->last_name), implode(' · ', array_filter([$e->employee_code, $e->email])), '/hr/employees/'.$e->id)),

            'customers' => $this->run(Customer::query(), ['code', 'name', 'email'], 'code', $contains, $prefix)
                ->map(fn (Customer $c) => $this->hit('customer', $c->id, $c->name, implode(' · ', array_filter([$c->code, $c->email])), '/sales/customers/'.$c->id)),

            'suppliers' => $this->run(Supplier::query(), ['code', 'name', 'email'], 'code', $contains, $prefix)
                ->map(fn (Supplier $s) => $this->hit('supplier', $s->id, $s->name, implode(' · ', array_filter([$s->code, $s->email])), '/procurement/suppliers/'.$s->id)),

            'products' => $this->run(Product::query(), ['sku', 'name', 'barcode'], 'sku', $contains, $prefix)
                ->map(fn (Product $p) => $this->hit('product', $p->id, $p->name, implode(' · ', array_filter([$p->sku, $p->barcode])), '/inventory/products/'.$p->id)),

            'invoices' => $this->invoices($contains, $prefix),

            'purchase_orders' => $this->run(PurchaseOrder::query()->with('supplier:id,name'), ['number'], 'number', $contains, $prefix)
                ->map(fn (PurchaseOrder $o) => $this->hit('purchase_order', $o->id, $o->number, $this->docSubtitle($o->supplier?->name, $o->currency, $o->total, $o->status), '/procurement/orders/'.$o->id)),

            'sales_orders' => $this->run(SalesOrder::query()->with('customer:id,name'), ['number'], 'number', $contains, $prefix)
                ->map(fn (SalesOrder $o) => $this->hit('sales_order', $o->id, $o->number, $this->docSubtitle($o->customer?->name, $o->currency, $o->total, $o->status), '/sales/orders/'.$o->id)),

            'quotations' => $this->run(Quotation::query()->with('customer:id,name'), ['number'], 'number', $contains, $prefix)
                ->map(fn (Quotation $q) => $this->hit('quotation', $q->id, $q->number, $this->docSubtitle($q->customer?->name, $q->currency, $q->total, $q->status), '/sales/quotations/'.$q->id)),

            'projects' => $this->run(Project::query()->visibleTo($user), ['number', 'code', 'name'], 'number', $contains, $prefix)
                ->map(fn (Project $p) => $this->hit('project', $p->id, $p->name, implode(' · ', array_filter([$p->number, $this->enumValue($p->status)])), '/projects/'.$p->id)),

            'documents' => $this->run(Document::query()->visibleTo($user), ['title'], 'title', $contains, $prefix)
                ->map(fn (Document $d) => $this->hit('document', $d->id, $d->title, $this->enumValue($d->category), '/documents?document='.$d->id)),

            'accounts' => $this->run(Account::query(), ['code', 'name'], 'code', $contains, $prefix)
                ->map(fn (Account $a) => $this->hit('account', $a->id, $a->code.' — '.$a->name, $this->enumValue($a->type), '/finance/accounts?account='.$a->id)),
        };
    }

    /**
     * Invoices carry a bare customer_id (no relation), so names come from one
     * extra tenant-scoped lookup.
     *
     * @return Collection<int, SearchHit>
     */
    private function invoices(string $contains, string $prefix): Collection
    {
        $invoices = $this->run(Invoice::query(), ['number'], 'number', $contains, $prefix);
        $customers = Customer::query()->whereKey($invoices->pluck('customer_id')->filter()->unique()->all())->pluck('name', 'id');

        return $invoices->map(fn (Invoice $i) => $this->hit(
            'invoice', $i->id, $i->number,
            $this->docSubtitle($customers[$i->customer_id] ?? null, $i->currency, $i->total, $i->status),
            '/finance/invoices/'.$i->id,
        ));
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $columns
     * @return Collection<int, TModel>
     */
    private function run(Builder $query, array $columns, string $rankColumn, string $contains, string $prefix, ?\Closure $extra = null): Collection
    {
        $table = $query->getModel()->getTable();

        return $query
            ->where(function (Builder $q) use ($columns, $contains, $extra, $table): void {
                foreach ($columns as $column) {
                    $q->orWhere("{$table}.{$column}", 'like', $contains);
                }
                if ($extra !== null) {
                    $extra($q);
                }
            })
            ->orderByRaw("CASE WHEN {$table}.{$rankColumn} LIKE ? THEN 0 ELSE 1 END", [$prefix])
            ->orderByDesc("{$table}.id")
            ->limit(self::PER_TYPE)
            ->get();
    }

    /**
     * @return SearchHit
     */
    private function hit(string $type, int $id, ?string $title, ?string $subtitle, string $url): array
    {
        return ['type' => $type, 'id' => $id, 'title' => (string) $title, 'subtitle' => $subtitle === '' ? null : $subtitle, 'url' => $url];
    }

    private function docSubtitle(?string $partner, ?string $currency, mixed $total, mixed $status): string
    {
        return implode(' · ', array_filter([
            $partner,
            $total === null ? null : trim(($currency ?? '').' '.Money::round(Money::normalize((string) $total), 2)),
            $this->enumValue($status),
        ]));
    }

    private function enumValue(mixed $value): ?string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : ($value === null ? null : (string) $value);
    }
}
