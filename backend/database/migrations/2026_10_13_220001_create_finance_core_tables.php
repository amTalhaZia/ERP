<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Finance core: chart of accounts, financial years/periods, tax rates, cash &
 * bank accounts and the general ledger (journal entries + lines).
 *
 * Storage notes: money is DECIMAL(18,4); statuses/types are short VARCHARs
 * backed by PHP enums; encrypted bank identifiers are bounded VARCHAR(500)
 * (Laravel's encrypted payload for a ≤64 char value). Indexes mirror the real
 * list/report access paths only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('code', 20);
            $table->string('name', 150);
            $table->string('type', 20);      // AccountType
            $table->string('subtype', 40)->nullable();
            $table->boolean('is_bank')->default(false);
            $table->boolean('is_cash')->default(false);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->char('currency', 3);
            $table->string('description', 500)->nullable();
            $table->decimal('opening_balance', 18, 4)->default(0);
            $table->date('opening_balance_date')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'type']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('financial_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('open'); // PeriodStatus
            $table->timestamps();

            $table->index(['company_id', 'start_date']);
        });

        Schema::create('financial_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_year_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('open'); // PeriodStatus
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'start_date']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('name', 100);
            $table->string('code', 20);
            $table->decimal('rate', 8, 4);   // percent, e.g. 15.0000
            $table->string('type', 20);      // TaxRateType
            $table->boolean('is_compound')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('bank_name', 150)->nullable();
            $table->string('account_number', 500)->nullable();   // encrypted
            $table->string('account_number_last4', 4)->nullable();
            $table->string('iban', 500)->nullable();             // encrypted
            $table->string('swift', 20)->nullable();
            $table->char('currency', 3);
            $table->decimal('opening_balance', 18, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('financial_period_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 30);
            $table->date('entry_date');
            $table->string('reference', 100)->nullable();
            $table->string('memo', 500)->nullable();
            $table->string('status', 20)->default('draft'); // JournalEntryStatus
            $table->string('source_type', 30)->nullable();   // invoice, bill, payment, payroll_run, manual…
            $table->unsignedBigInteger('source_id')->nullable();
            $table->decimal('total_debit', 18, 4)->default(0);
            $table->decimal('total_credit', 18, 4)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('reverses_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('reversed_by_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'entry_date']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'source_type', 'source_id']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('partner_type', 30)->nullable();  // customer, supplier, employee (AR/AP sub-ledger)
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->foreignId('tax_rate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 255)->nullable();
            $table->decimal('debit', 18, 4)->default(0);
            $table->decimal('credit', 18, 4)->default(0);
            $table->timestamps();

            $table->index(['account_id', 'journal_entry_id']);
            $table->index(['company_id', 'account_id']);
            $table->index(['partner_type', 'partner_id']);
        });

        // One side only, never negative (MySQL 8.0.16+ enforces CHECK).
        DB::statement(
            'ALTER TABLE journal_lines ADD CONSTRAINT journal_lines_one_side_chk '
            .'CHECK (debit >= 0 AND credit >= 0 AND NOT (debit > 0 AND credit > 0))'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('financial_periods');
        Schema::dropIfExists('financial_years');
        Schema::dropIfExists('accounts');
    }
};
