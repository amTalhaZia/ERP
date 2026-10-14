<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Procurement: suppliers and their contacts.
 *
 * bank_account_number is stored encrypted (Eloquent cast); only the last four
 * digits are kept in clear for display.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 150);
            $table->string('type', 20)->default('company');
            $table->string('email', 150)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('website', 200)->nullable();
            $table->string('tax_number', 60)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->unsignedSmallInteger('payment_terms_days')->default(30);
            $table->decimal('credit_limit', 18, 4)->nullable();
            $table->string('address_line1', 200)->nullable();
            $table->string('address_line2', 200)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->char('country', 2)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('bank_name', 150)->nullable();
            $table->text('bank_account_number')->nullable();
            $table->string('bank_account_last4', 4)->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->json('categories')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_approved')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code'], 'sup_co_code_uq');
            $table->index(['company_id', 'is_active'], 'sup_co_is_active_idx');
            $table->index(['company_id', 'name'], 'sup_co_name_idx');
        });

        Schema::create('supplier_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('title', 100)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 40)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['company_id', 'supplier_id'], 'supc_co_supplier_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_contacts');
        Schema::dropIfExists('suppliers');
    }
};
