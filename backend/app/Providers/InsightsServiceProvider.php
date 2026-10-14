<?php

namespace App\Providers;

use App\Models\Reports\ExportJob;
use App\Models\User;
use App\Policies\Reports\ExportJobPolicy;
use App\Services\Reports\Definitions\ReportDefinition;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Cross-cutting read features: dashboard, approvals inbox, reports/exports
 * and global search. Registers the ExportJob policy and the `runReport` ability (any of the report's declared permissions).
 */
class InsightsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(ExportJob::class, ExportJobPolicy::class);

        Gate::define('runReport', fn (User $user, ReportDefinition $definition) => $definition->authorizedFor($user));
    }
}
