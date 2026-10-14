<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sales & CRM: quotations and their lines.
 *
 * `product_id` deliberately has NO foreign key: the products table is owned by
 * the Inventory module (created concurrently). Lines carry description,
 * unit_price and uom snapshots so a quotation remains readable even if the
 * product changes later. The integration phase may add the FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30);
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->date('valid_until')->nullable();
            $table->string('status', 20)->default('draft');
            $table->char('currency', 3);
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('discount_total', 18, 4)->default(0);
            $table->decimal('tax_total', 18, 4)->default(0);
            $table->decimal('total', 18, 4)->default(0);
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->foreignId('sales_rep_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // FK added in the sales orders migration (table created later).
            $table->unsignedBigInteger('converted_sales_order_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'customer_id']);
            $table->index(['company_id', 'date']);
            $table->index(['company_id', 'sales_rep_id']);
        });

        Schema::create('quotation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('description', 500);
            $table->decimal('quantity', 18, 4);
            $table->string('uom', 10)->nullable();
            $table->decimal('unit_price', 18, 4);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('line_subtotal', 18, 4);
            $table->decimal('tax_amount', 18, 4);
            $table->decimal('line_total', 18, 4);
            $table->timestamps();

            $table->index(['quotation_id', 'line_no']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_lines');
        Schema::dropIfExists('quotations');
    }
};
