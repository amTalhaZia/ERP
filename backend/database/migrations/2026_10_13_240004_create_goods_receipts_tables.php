<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Procurement: goods receipt notes (GRN) against purchase orders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30);
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->date('received_date');
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('supplier_delivery_note', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number'], 'grn_co_number_uq');
            $table->index(['company_id', 'status'], 'grn_co_status_idx');
            $table->index(['company_id', 'purchase_order_id'], 'grn_co_purchase_order_idx');
            $table->index(['company_id', 'received_date'], 'grn_co_received_date_idx');
        });

        Schema::create('goods_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('description', 255);
            $table->decimal('quantity_ordered', 18, 4);
            $table->decimal('quantity_received', 18, 4);
            $table->decimal('quantity_rejected', 18, 4)->default(0);
            $table->string('rejection_reason', 255)->nullable();
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->string('batch_number', 60)->nullable();
            $table->date('expires_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'goods_receipt_id'], 'grnl_co_goods_receipt_idx');
            $table->index('purchase_order_line_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_lines');
        Schema::dropIfExists('goods_receipts');
    }
};
