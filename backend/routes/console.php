<?php

use App\Models\Company;
use App\Models\Reports\ExportJob;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hosted demo boot step: seed the demo data once, on an empty database only.
// Needs DEMO_BOOTSTRAP=true so it can never fill a real installation by accident.
Artisan::command('erp:demo-bootstrap', function () {
    if (! filter_var(env('DEMO_BOOTSTRAP', false), FILTER_VALIDATE_BOOL)) {
        $this->warn('DEMO_BOOTSTRAP is not enabled — skipped.');

        return 0;
    }
    if (Company::query()->withoutGlobalScopes()->exists()) {
        $this->info('Database already has companies — demo seed skipped.');

        return 0;
    }
    $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);

    return 0;
})->purpose('Seed demo data on an empty hosted demo database (DEMO_BOOTSTRAP=true)');

// Daily housekeeping (server cron: `* * * * * php artisan schedule:run`). Times are UTC.
Schedule::command('erp:prune')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('documents:apply-retention')->dailyAt('03:00')->withoutOverlapping();
Schedule::command('inventory:check-reorder')->dailyAt('07:00')->withoutOverlapping();
Schedule::command('model:prune', ['--model' => [ExportJob::class]])->dailyAt('03:30')->withoutOverlapping();
