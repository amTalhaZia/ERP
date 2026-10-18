<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Approximate place of the request (from the hosting edge), shown next to the IP in the audit log. */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->char('country_code', 2)->nullable()->after('ip_address');
            $table->string('city', 100)->nullable()->after('country_code');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['country_code', 'city']);
        });
    }
};
