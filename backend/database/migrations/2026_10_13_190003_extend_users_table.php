<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 40)->nullable()->after('email');
            $table->string('avatar_path', 255)->nullable()->after('phone');
            $table->boolean('is_active')->default(true)->after('password');
            // platform.owner | platform.admin | NULL — platform staff only.
            $table->string('platform_role', 30)->nullable()->after('is_active');
            $table->json('preferences')->nullable()->after('platform_role');
            $table->text('two_factor_secret')->nullable()->after('preferences');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            $table->timestamp('password_changed_at')->nullable()->after('two_factor_confirmed_at');
            $table->timestamp('last_login_at')->nullable()->after('password_changed_at');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->timestamp('deactivated_at')->nullable()->after('last_login_ip');

            $table->index(['is_active', 'platform_role']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'platform_role']);
            $table->dropColumn([
                'phone', 'avatar_path', 'is_active', 'platform_role', 'preferences',
                'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
                'password_changed_at', 'last_login_at', 'last_login_ip', 'deactivated_at',
            ]);
        });
    }
};
