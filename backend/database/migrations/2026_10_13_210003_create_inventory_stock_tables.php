<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock ledger.
 *
 *  - stock_movements is APPEND-ONLY: the source of truth (ARCHITECTURE §8).
 *  - stock_levels is the per (product, warehouse, location) running balance,
 *    maintained by RecordStockMovementAction under a row lock. location_id is
 *    nullable, so uniqueness cannot be a DB constraint (NULLs are distinct in
 *    MySQL unique indexes); the action serialises on the product row and
 *    find-or-creates the level under lock instead.
 *  - batches / serial_numbers give lot & unit traceability.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('batch_number', 60);
            $table->date('manufactured_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'batch_number']);
            $table->index(['company_id', 'expires_at']);
        });

        Schema::create('serial_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->string('serial', 80);
            $table->string('status', 12)->default('in_stock');
            $table->timestamps();

            $table->unique(['product_id', 'serial']);
            $table->index(['company_id', 'warehouse_id', 'status']);
        });

        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('storage_locations')->nullOnDelete();
            $table->decimal('quantity_on_hand', 18, 4)->default(0);
            $table->decimal('quantity_reserved', 18, 4)->default(0);
            $table->decimal('average_cost', 18, 4)->default(0);
            $table->timestamps();

            $table->index(['company_id', 'product_id', 'warehouse_id', 'location_id'], 'stock_levels_lookup_idx');
            $table->index(['company_id', 'warehouse_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('storage_locations')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->string('type', 20);
            // Signed: positive = into the warehouse, negative = out.
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->decimal('total_cost', 18, 4)->default(0);
            $table->decimal('balance_after', 18, 4);
            $table->string('reference_type', 60)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reason', 200)->nullable();
            $table->dateTime('moved_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'product_id', 'moved_at']);
            $table->index(['company_id', 'warehouse_id', 'moved_at']);
            $table->index(['company_id', 'type', 'moved_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_levels');
        Schema::dropIfExists('serial_numbers');
        Schema::dropIfExists('batches');
    }
};
