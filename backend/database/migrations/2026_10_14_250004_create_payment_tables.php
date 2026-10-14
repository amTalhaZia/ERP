<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Receipts (from customers) and payments (to suppliers / employees) in one
 * table, with allocations against invoices, bills and expense claims.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 30);
            $table->string('type', 20);           // PaymentType
            $table->string('partner_type', 20);   // PaymentPartnerType
            $table->unsignedBigInteger('partner_id');
            $table->date('payment_date');
            $table->string('method', 20);         // PaymentMethod
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
            $table->decimal('amount', 18, 4);
            $table->char('currency', 3);
            $table->string('reference', 100)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->string('status', 20)->default('posted');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'type', 'status']);
            $table->index(['company_id', 'partner_type', 'partner_id']);
            $table->index(['company_id', 'payment_date']);
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('allocatable_type', 20);   // invoice | bill | expense_claim
            $table->unsignedBigInteger('allocatable_id');
            $table->decimal('amount', 18, 4);
            $table->timestamps();

            $table->index(['allocatable_type', 'allocatable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
    }
};
