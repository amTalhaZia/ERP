<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily housekeeping (server cron: `* * * * * php artisan schedule:run`). Times are UTC.
Schedule::command('erp:prune')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('documents:apply-retention')->dailyAt('03:00')->withoutOverlapping();
Schedule::command('inventory:check-reorder')->dailyAt('07:00')->withoutOverlapping();
Schedule::command('model:prune', ['--model' => [App\Models\Reports\ExportJob::class]])->dailyAt('03:30')->withoutOverlapping();
