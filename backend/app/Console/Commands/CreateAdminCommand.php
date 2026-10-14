<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Creates the first platform owner. Interactive by default; the password is
 * never accepted on the command line in production (shell history).
 *
 * Production additionally requires ERP_ALLOW_ADMIN_BOOTSTRAP=true and --force,
 * and refuses to run once a platform owner already exists.
 */
class CreateAdminCommand extends Command
{
    protected $signature = 'erp:create-admin
        {--name= : Full name}
        {--email= : Email address}
        {--password= : Password (development only; prompted otherwise)}
        {--force : Required in production}';

    protected $description = 'Securely create the initial platform owner account';

    public function handle(AuditLogger $audit): int
    {
        if (app()->isProduction()) {
            if (! config('erp.allow_admin_bootstrap') || ! $this->option('force')) {
                $this->components->error('In production set ERP_ALLOW_ADMIN_BOOTSTRAP=true and pass --force.');

                return self::FAILURE;
            }
            if (User::query()->where('platform_role', Role::PLATFORM_OWNER)->exists()) {
                $this->components->error('A platform owner already exists. Manage further admins from the platform panel.');

                return self::FAILURE;
            }
            if ($this->option('password')) {
                $this->components->error('Do not pass --password in production; you will be prompted.');

                return self::FAILURE;
            }
        }

        $name = $this->option('name') ?: $this->ask('Full name');
        $email = $this->option('email') ?: $this->ask('Email');
        $password = $this->option('password') ?: $this->secret('Password (min '.config('erp.security.password_min_length').' chars, letters + numbers)');

        $validator = Validator::make(compact('name', 'email', 'password'), [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'is_active' => true,
            'platform_role' => Role::PLATFORM_OWNER,
        ]);
        $user->forceFill(['email_verified_at' => now(), 'password_changed_at' => now()])->saveQuietly();

        $audit->log('platform.owner_created', $user, context: ['via' => 'erp:create-admin']);

        $this->components->info("Platform owner created: {$user->email}");
        $this->components->warn('Enable two-factor authentication for this account after first login.');

        return self::SUCCESS;
    }
}
