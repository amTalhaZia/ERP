<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll runs, payslips (one per employee per run, with an employee
 * snapshot) and payslip lines (component snapshot + amount).
 *
 * `payroll_runs.scope_key` holds "start|end|branch|department" while the run
 * is not cancelled and NULL once cancelled, so the composite unique enforces
 * "one live run per company + period + scope" (MySQL ignores NULLs).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30);
            $table->string('name', 150);
            $table->date('period_start');
            $table->date('period_end');
            $table->date('payment_date');
            $table->char('currency', 3);
            $table->string('status', 20)->default('draft');
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('scope_key', 80)->nullable();
            $table->decimal('total_gross', 18, 4)->default(0);
            $table->decimal('total_tax', 18, 4)->default(0);
            $table->decimal('total_deductions', 18, 4)->default(0);
            $table->decimal('total_employer_contributions', 18, 4)->default(0);
            $table->decimal('total_net', 18, 4)->default(0);
            $table->unsignedInteger('employee_count')->default(0);
            $table->timestamp('calculated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->string('notes', 1000)->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->unique(['company_id', 'scope_key'], 'payroll_runs_scope_unique');
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'period_end']);
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_salary_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 30)->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('employee_code', 20);
            $table->string('employee_name', 200);
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('department_name', 150)->nullable();
            $table->string('designation_title', 150)->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('bank_name', 150)->nullable();
            $table->string('bank_account_last4', 4)->nullable();
            $table->char('currency', 3);
            $table->decimal('base_salary', 18, 4)->default(0);
            $table->decimal('working_days', 5, 1)->default(0);
            $table->decimal('unpaid_days', 5, 1)->default(0);
            $table->decimal('paid_days', 5, 1)->default(0);
            $table->decimal('earned_base', 18, 4)->default(0);
            $table->decimal('gross', 18, 4)->default(0);
            $table->decimal('taxable', 18, 4)->default(0);
            $table->decimal('tax', 18, 4)->default(0);
            $table->decimal('total_deductions', 18, 4)->default(0);
            $table->decimal('employer_contributions', 18, 4)->default(0);
            $table->decimal('net', 18, 4)->default(0);
            $table->json('warnings')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id'], 'payslips_run_employee_unique');
            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'employee_id', 'status'], 'payslips_employee_status_idx');
            $table->index(['company_id', 'status']);
        });

        Schema::create('payslip_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payslip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pay_component_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 30);
            $table->string('name', 100);
            $table->string('kind', 30);
            $table->string('calculation', 20)->nullable();
            $table->decimal('rate', 8, 4)->nullable();
            $table->decimal('amount', 18, 4);
            $table->boolean('is_taxable')->default(false);
            $table->boolean('affects_gross')->default(true);
            $table->string('gl_account_code', 20);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslip_lines');
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_runs');
    }
};
