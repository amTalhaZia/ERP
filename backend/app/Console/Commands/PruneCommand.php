<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Storage control: removes rows past their retention window in bounded
 * chunks. Financial and HR records are never touched — only operational logs.
 */
class PruneCommand extends Command
{
    protected $signature = 'erp:prune {--dry-run : Report counts without deleting}';

    protected $description = 'Delete audit logs, read notifications and other operational rows past retention (config/erp.php)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $retention = config('erp.retention_days');

        $this->prune('audit_logs', 'created_at', $retention['audit_logs'] ?? null, $dry,
            // Financial postings and permission changes are kept regardless of age.
            fn ($q) => $q->where('action', 'not like', 'journal%')->where('action', 'not like', 'roles.%')->where('action', 'not like', 'platform.%'));

        $this->prune('notifications', 'read_at', $retention['notifications_read'] ?? null, $dry, fn ($q) => $q->whereNotNull('read_at'));

        $this->prune('export_jobs', 'created_at', $retention['export_files'] ?? null, $dry);

        return self::SUCCESS;
    }

    private function prune(string $table, string $column, ?int $days, bool $dry, ?callable $scope = null): void
    {
        if (! $days || ! Schema::hasTable($table)) {
            return;
        }

        $cutoff = now()->subDays($days);
        $query = DB::table($table)->where($column, '<', $cutoff);
        if ($scope) {
            $scope($query);
        }

        $count = (clone $query)->count();

        if ($dry) {
            $this->components->twoColumnDetail("{$table} older than {$days}d", "{$count} rows (dry run)");

            return;
        }

        $deleted = 0;
        do {
            $batch = (clone $query)->orderBy('id')->limit(1000)->pluck('id');
            if ($batch->isEmpty()) {
                break;
            }
            $deleted += DB::table($table)->whereIn('id', $batch)->delete();
        } while (true);

        $this->components->twoColumnDetail("{$table} older than {$days}d", "{$deleted} rows deleted");
    }
}
