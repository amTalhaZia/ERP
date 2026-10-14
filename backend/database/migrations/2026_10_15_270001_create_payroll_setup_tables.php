<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll configuration: pay component catalogue, configurable withholding
 * tax tables (jurisdiction rules are the customer's responsibility), salary
 * structures and per-employee salary history with component overrides.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->string('kind', 30);
            $table->string('calculation', 20);
            $table->decimal('default_amount', 18, 4)->default(0);
            $table->decimal('default_rate', 8, 4)->default(0);
            $table->boolean('is_taxable')->default(true);
            $table->boolean('reduces_taxable')->default(false);
            $table->boolean('affects_gross')->default(true);
            $table->string('gl_account_code', 20)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('tax_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->date('effective_from');
            $table->string('period', 20)->default('monthly');
            $table->boolean('is_active')->default(true);
            $table->string('notes', 1000)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'is_active', 'effective_from'], 'tax_tables_active_lookup');
        });

        Schema::create('tax_brackets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tax_table_id')->constrained()->cascadeOnDelete();
            $table->decimal('from_amount', 18, 4)->default(0);
            $table->decimal('to_amount', 18, 4)->nullable();
            $table->decimal('rate', 8, 4)->default(0);
            $table->decimal('fixed_amount', 18, 4)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('salary_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->char('currency', 3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        Schema::create('salary_structure_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pay_component_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 18, 4)->nullable();
            $table->decimal('rate', 8, 4)->nullable();
            $table->timestamps();

            $table->unique(['salary_structure_id', 'pay_component_id'], 'ssc_structure_component_unique');
        });

        Schema::create('employee_salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_structure_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('base_salary', 18, 4);
            $table->char('currency', 3);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('pay_frequency', 20)->default('monthly');
            $table->string('notes', 1000)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'effective_from'], 'emp_salaries_effective_unique');
            $table->index(['company_id', 'employee_id', 'effective_from'], 'emp_salaries_lookup');
        });

        Schema::create('employee_salary_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_salary_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pay_component_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 18, 4)->nullable();
            $table->decimal('rate', 8, 4)->nullable();
            $table->timestamps();

            $table->unique(['employee_salary_id', 'pay_component_id'], 'esc_salary_component_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_components');
        Schema::dropIfExists('employee_salaries');
        Schema::dropIfExists('salary_structure_components');
        Schema::dropIfExists('salary_structures');
        Schema::dropIfExists('tax_brackets');
        Schema::dropIfExists('tax_tables');
        Schema::dropIfExists('pay_components');
    }
};
