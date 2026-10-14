<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projects: time entries and project expenses. Expenses are a project cost
 * ledger only — posting them to the general ledger is out of scope.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('hours', 6, 2);
            $table->string('description', 500)->nullable();
            $table->boolean('is_billable')->default(true);
            $table->decimal('hourly_rate', 18, 4)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'project_id', 'date'], 'time_entries_project_date_idx');
            $table->index(['company_id', 'user_id', 'date'], 'time_entries_user_date_idx');
        });

        Schema::create('project_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('category', 60);
            $table->string('description', 500)->nullable();
            $table->decimal('amount', 18, 4);
            $table->char('currency', 3)->default('USD');
            $table->foreignId('receipt_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'project_id', 'date'], 'project_expenses_project_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_expenses');
        Schema::dropIfExists('time_entries');
    }
};
