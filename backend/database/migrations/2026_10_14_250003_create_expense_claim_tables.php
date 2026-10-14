<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employee expense claims. employee_id is a plain reference (HR owns the
 * table); the reimbursement partner in the ledger is the submitting user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 30);
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->date('claim_date');
            $table->char('currency', 3);
            $table->string('status', 20)->default('draft');
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('tax_total', 18, 4)->default(0);
            $table->decimal('total', 18, 4)->default(0);
            $table->decimal('amount_paid', 18, 4)->default(0);
            $table->decimal('balance_due', 18, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'user_id']);
            $table->index('employee_id');
        });

        Schema::create('expense_claim_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_claim_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->date('expense_date');
            $table->string('category', 60);
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('description', 255)->nullable();
            $table->decimal('amount', 18, 4);
            $table->foreignId('tax_rate_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('line_total', 18, 4)->default(0);
            $table->string('receipt_path', 255)->nullable();
            $table->string('receipt_original_name', 255)->nullable();
            $table->timestamps();

            $table->index(['expense_claim_id', 'line_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_claim_lines');
        Schema::dropIfExists('expense_claims');
    }
};
