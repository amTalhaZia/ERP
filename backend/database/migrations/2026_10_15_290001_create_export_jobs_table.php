<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Queued report exports (> 2000 rows). Files live on the private `local`
 * disk; the row keeps only the path. Rows past `expires_at` are pruned
 * (model:prune) together with their file.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('report_key', 64);
            $table->string('format', 10);
            $table->json('params')->nullable();
            $table->string('status', 20);
            $table->string('file_path', 255)->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'user_id', 'created_at'], 'export_jobs_owner_idx');
            $table->index('expires_at', 'export_jobs_expires_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_jobs');
    }
};
