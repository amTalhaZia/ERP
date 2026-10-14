<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory catalogue: units of measure, product categories (tree) and products.
 *
 * `products` deliberately has NO quantity column — on-hand is derived from
 * stock_levels / stock_movements (ARCHITECTURE §8). `attributes` JSON is for
 * sparse per-product specs only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units_of_measure', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('symbol', 12);
            $table->unsignedTinyInteger('precision')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'symbol']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('name', 120);
            $table->string('code', 30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'parent_id']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->foreignId('uom_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->string('sku', 60);
            $table->string('barcode', 64)->nullable();
            $table->string('name', 200);
            $table->string('description', 1000)->nullable();
            $table->string('type', 20)->default('goods');
            $table->decimal('cost_price', 18, 4)->default(0);
            $table->decimal('sale_price', 18, 4)->default(0);
            $table->char('currency', 3)->default('USD');
            $table->decimal('tax_rate', 8, 4)->nullable();
            $table->decimal('reorder_level', 18, 4)->default(0);
            $table->decimal('reorder_quantity', 18, 4)->default(0);
            $table->boolean('track_batches')->default(false);
            $table->boolean('track_serials')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('image_path', 255)->nullable();
            $table->json('attributes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'sku']);
            $table->index(['company_id', 'barcode']);
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'category_id']);
            $table->index(['company_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('units_of_measure');
    }
};
