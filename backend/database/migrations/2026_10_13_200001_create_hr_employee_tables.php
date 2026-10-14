<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR core: designations, employees and employment contracts. Also adds the
 * deferred FK from departments.manager_employee_id → employees.
 *
 * Sensitive identifiers (national id, bank account, IBAN, tax id) are stored
 * encrypted (TEXT) and never indexed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('code', 20);
            $table->unsignedTinyInteger('level')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('employee_code', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 150);
            $table->string('phone', 40)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->nullable();
            $table->text('national_id')->nullable();
            $table->string('marital_status', 20)->nullable();
            $table->string('address_line1', 200)->nullable();
            $table->string('address_line2', 200)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->char('country', 2)->nullable();
            $table->string('emergency_contact_name', 150)->nullable();
            $table->string('emergency_contact_phone', 40)->nullable();
            $table->string('emergency_contact_relationship', 60)->nullable();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('employment_type', 20)->default('full_time');
            $table->string('status', 20)->default('probation');
            $table->date('hired_at')->nullable();
            $table->date('probation_ends_at')->nullable();
            $table->date('terminated_at')->nullable();
            $table->string('termination_reason', 500)->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->string('bank_name', 150)->nullable();
            $table->text('bank_account_number')->nullable();
            $table->text('bank_iban')->nullable();
            $table->text('tax_identifier')->nullable();
            $table->decimal('base_salary', 18, 4)->nullable();
            $table->char('currency', 3)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'employee_code']);
            $table->unique(['company_id', 'email']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'department_id']);
            $table->index(['company_id', 'branch_id']);
            $table->index(['company_id', 'user_id']);
            $table->index(['company_id', 'manager_id']);
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('manager_employee_id')->references('id')->on('employees')->nullOnDelete();
        });

        Schema::create('employment_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('contract_type', 20);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('salary', 18, 4);
            $table->char('currency', 3);
            $table->decimal('working_hours_per_week', 5, 2)->default(40);
            $table->string('document_path', 255)->nullable();
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'employee_id']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_contracts');

        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['manager_employee_id']);
        });

        Schema::dropIfExists('employees');
        Schema::dropIfExists('designations');
    }
};
