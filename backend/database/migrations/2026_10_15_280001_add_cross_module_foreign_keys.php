<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cross-module integration: the Sales, Procurement and AR/AP tables were built
 * in parallel with Inventory, so their product / warehouse / partner ids were
 * plain indexed references. Now that all modules exist, enforce them.
 *
 *  - nullable columns → ON DELETE SET NULL (orphaned ids are nulled first),
 *  - NOT NULL columns → ON DELETE RESTRICT (orphans make the migration fail
 *    loudly instead of silently rewriting financial documents).
 *
 * quotations / sales_orders.customer_id and purchase_orders.supplier_id were
 * already constrained at creation and are not touched. Every FK is guarded by
 * Schema::hasTable so module-only test suites (which migrate a subset of the
 * tables) keep working. A supporting index is created only when no existing
 * index starts with the column, and down() drops exactly what up() added.
 */
return new class extends Migration
{
    /** @var array<int, array{table: string, column: string, references: string, name: string}> */
    private const FOREIGN_KEYS = [
        ['table' => 'quotation_lines', 'column' => 'product_id', 'references' => 'products', 'name' => 'ql_product_fk'],
        ['table' => 'sales_order_lines', 'column' => 'product_id', 'references' => 'products', 'name' => 'sol_product_fk'],
        ['table' => 'purchase_requisition_lines', 'column' => 'product_id', 'references' => 'products', 'name' => 'prl_product_fk'],
        ['table' => 'purchase_order_lines', 'column' => 'product_id', 'references' => 'products', 'name' => 'pol_product_fk'],
        ['table' => 'goods_receipt_lines', 'column' => 'product_id', 'references' => 'products', 'name' => 'grnl_product_fk'],
        ['table' => 'purchase_return_lines', 'column' => 'product_id', 'references' => 'products', 'name' => 'prtl_product_fk'],
        ['table' => 'invoice_lines', 'column' => 'product_id', 'references' => 'products', 'name' => 'invl_product_fk'],
        ['table' => 'bill_lines', 'column' => 'product_id', 'references' => 'products', 'name' => 'bill_l_product_fk'],
        ['table' => 'sales_orders', 'column' => 'warehouse_id', 'references' => 'warehouses', 'name' => 'so_warehouse_fk'],
        ['table' => 'purchase_orders', 'column' => 'warehouse_id', 'references' => 'warehouses', 'name' => 'po_warehouse_fk'],
        ['table' => 'goods_receipts', 'column' => 'warehouse_id', 'references' => 'warehouses', 'name' => 'grn_warehouse_fk'],
        ['table' => 'invoices', 'column' => 'customer_id', 'references' => 'customers', 'name' => 'inv_customer_fk'],
        ['table' => 'bills', 'column' => 'supplier_id', 'references' => 'suppliers', 'name' => 'bill_supplier_fk'],
    ];

    public function up(): void
    {
        foreach (self::FOREIGN_KEYS as $fk) {
            if (! $this->applicable($fk) || $this->foreignKeyExists($fk['table'], $fk['name'])) {
                continue;
            }

            $nullable = $this->isNullable($fk['table'], $fk['column']);

            if ($nullable) {
                DB::table($fk['table'])
                    ->whereNotNull($fk['column'])
                    ->whereNotExists(fn ($query) => $query->selectRaw('1')->from($fk['references'])
                        ->whereColumn("{$fk['references']}.id", "{$fk['table']}.{$fk['column']}"))
                    ->update([$fk['column'] => null]);
            }

            $needsIndex = ! $this->hasLeadingIndex($fk['table'], $fk['column']);

            Schema::table($fk['table'], function (Blueprint $table) use ($fk, $nullable, $needsIndex): void {
                if ($needsIndex) {
                    $table->index($fk['column'], $this->indexName($fk));
                }

                $foreign = $table->foreign($fk['column'], $fk['name'])->references('id')->on($fk['references']);
                $nullable ? $foreign->nullOnDelete() : $foreign->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::FOREIGN_KEYS) as $fk) {
            if (! Schema::hasTable($fk['table'])) {
                continue;
            }

            if ($this->foreignKeyExists($fk['table'], $fk['name'])) {
                Schema::table($fk['table'], fn (Blueprint $table) => $table->dropForeign($fk['name']));
            }

            if (Schema::hasIndex($fk['table'], $this->indexName($fk))) {
                Schema::table($fk['table'], fn (Blueprint $table) => $table->dropIndex($this->indexName($fk)));
            }
        }
    }

    /**
     * @param  array{table: string, column: string, references: string, name: string}  $fk
     */
    private function applicable(array $fk): bool
    {
        return Schema::hasTable($fk['table'])
            && Schema::hasTable($fk['references'])
            && Schema::hasColumn($fk['table'], $fk['column']);
    }

    /**
     * @param  array{table: string, column: string, references: string, name: string}  $fk
     */
    private function indexName(array $fk): string
    {
        return str_replace('_fk', '_idx', $fk['name']);
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        return collect(Schema::getForeignKeys($table))->contains(fn (array $foreignKey) => $foreignKey['name'] === $name);
    }

    private function hasLeadingIndex(string $table, string $column): bool
    {
        return collect(Schema::getIndexes($table))->contains(fn (array $index) => ($index['columns'][0] ?? null) === $column);
    }

    private function isNullable(string $table, string $column): bool
    {
        return (bool) collect(Schema::getColumns($table))->firstWhere('name', $column)['nullable'];
    }
};
