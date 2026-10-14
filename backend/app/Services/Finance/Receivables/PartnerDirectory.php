<?php

namespace App\Services\Finance\Receivables;

use App\Enums\Finance\PaymentPartnerType;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-scoped, read-only lookups into tables owned by other modules
 * (customers, suppliers, sales/purchase orders, products, employees). Raw
 * queries on purpose: AR/AP must not depend on those modules' Eloquent models,
 * which are developed concurrently.
 */
class PartnerDirectory
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * Whether $id exists in $table for the current company. When the owning
     * module is not installed (table missing) the reference cannot be checked
     * and is refused.
     */
    public function exists(string $table, int|string $id, ?int $companyId = null): bool
    {
        if (! is_numeric($id) || ! Schema::hasTable($table)) {
            return false;
        }

        $query = DB::table($table)
            ->where('company_id', $companyId ?? $this->tenant->id())
            ->where('id', (int) $id);

        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query->exists();
    }

    public function partnerExists(PaymentPartnerType $type, int $id, ?int $companyId = null): bool
    {
        $companyId ??= $this->tenant->id();

        return match ($type) {
            PaymentPartnerType::Customer => $this->exists('customers', $id, $companyId),
            PaymentPartnerType::Supplier => $this->exists('suppliers', $id, $companyId),
            PaymentPartnerType::Employee => DB::table('company_user')
                ->where('company_id', $companyId)->where('user_id', $id)->where('is_active', true)->exists(),
        };
    }

    /**
     * Payment terms (days) and currency of a customer / supplier, if known.
     *
     * @return array{payment_terms_days: ?int, currency: ?string}
     */
    public function terms(string $table, int $id): array
    {
        if (! Schema::hasTable($table)) {
            return ['payment_terms_days' => null, 'currency' => null];
        }

        $row = DB::table($table)->where('company_id', $this->tenant->id())->where('id', $id)
            ->first(['payment_terms_days', 'currency']);

        return [
            'payment_terms_days' => $row?->payment_terms_days === null ? null : (int) $row->payment_terms_days,
            'currency' => $row?->currency,
        ];
    }

    /**
     * Display data for a customer / supplier (PDF, statements, aging).
     *
     * @return array<string, mixed>|null
     */
    public function details(string $table, int $id, ?int $companyId = null): ?array
    {
        if (! Schema::hasTable($table)) {
            return null;
        }

        $row = DB::table($table)->where('company_id', $companyId ?? $this->tenant->id())->where('id', $id)->first();

        return $row === null ? null : (array) $row;
    }

    /**
     * Set a `partner` pseudo-relation ({id, name}) on each model, with one
     * query per call — resources expose it via whenLoaded('partner').
     *
     * @param  iterable<Model>  $models
     */
    public function attach(iterable $models, PaymentPartnerType $type, string $column): void
    {
        $models = collect($models);
        $names = $this->names($type, $models->map(fn (Model $m) => (int) $m->getAttribute($column))->all());

        $models->each(function (Model $model) use ($names, $column): void {
            $id = (int) $model->getAttribute($column);
            $model->setRelation('partner', ['id' => $id, 'name' => $names[$id] ?? null]);
        });
    }

    /**
     * id => name map for many partners at once.
     *
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    public function names(PaymentPartnerType $type, array $ids, ?int $companyId = null): array
    {
        $ids = array_values(array_unique(array_filter($ids)));

        if ($ids === []) {
            return [];
        }

        $companyId ??= $this->tenant->id();

        if ($type === PaymentPartnerType::Employee) {
            return DB::table('users')->whereIn('id', $ids)->pluck('name', 'id')->all();
        }

        $table = $type === PaymentPartnerType::Customer ? 'customers' : 'suppliers';

        if (! Schema::hasTable($table)) {
            return [];
        }

        return DB::table($table)->where('company_id', $companyId)->whereIn('id', $ids)->pluck('name', 'id')->all();
    }
}
