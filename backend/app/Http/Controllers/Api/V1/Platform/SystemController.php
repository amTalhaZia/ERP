<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Safe system overview for platform staff: versions, drivers, counts and an
 * approximate per-table size report. Never exposes hosts, credentials, env
 * values or a query console.
 */
class SystemController extends ApiController
{
    private const PERMISSION = 'platform.system.view';

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        Gate::allowIf($user->hasPermission(self::PERMISSION));

        return response()->json(['data' => [
            'app' => [
                'name' => config('app.name'),
                'version' => (string) config('erp.version', '1.0.0'),
                'framework' => app()->version(),
                'environment' => app()->environment(),
                'debug' => (bool) config('app.debug'),
                'timezone' => config('app.timezone'),
                'maintenance' => app()->isDownForMaintenance(),
            ],
            'php' => [
                'version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => (int) ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
            ],
            'database' => $this->database(),
            'services' => [
                'queue' => config('queue.default'),
                'cache' => config('cache.default'),
                'session' => config('session.driver'),
                'mail' => config('mail.default'),
                'filesystem' => config('filesystems.default'),
            ],
            'counts' => [
                'companies' => Company::query()->count(),
                'active_companies' => Company::query()->where('is_active', true)->count(),
                'users' => User::query()->count(),
                'active_users' => User::query()->where('is_active', true)->count(),
                'platform_users' => User::query()->whereNotNull('platform_role')->count(),
            ],
            'storage' => $this->storage(),
            'tables' => $this->tables(),
            'generated_at' => now()->toJSON(),
        ]]);
    }

    /**
     * Storage used by each company: rows it owns in every tenant table, an
     * estimated on-disk size (rows × the table's average row size) and the
     * bytes of files it has uploaded. Counts only — never record contents.
     * Cached for 10 minutes; ?refresh=1 recomputes.
     */
    public function usage(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        Gate::allowIf($user->hasPermission(self::PERMISSION));

        if ($request->boolean('refresh')) {
            Cache::forget('platform.company_usage');
        }

        $data = Cache::remember('platform.company_usage', now()->addMinutes(10), fn () => $this->computeUsage());

        return response()->json(['data' => $data]);
    }

    /**
     * @return array{companies: list<array<string, mixed>>, shared_bytes: int, generated_at: string}
     */
    private function computeUsage(): array
    {
        $tables = collect(DB::connection()->getDriverName() === 'mysql' ? DB::select(
            "SELECT t.TABLE_NAME AS name, t.TABLE_ROWS AS table_rows, (t.DATA_LENGTH + t.INDEX_LENGTH) AS size_bytes,
                    MAX(c.COLUMN_NAME = 'company_id') AS tenant, MAX(c.COLUMN_NAME = 'size_bytes') AS has_files
             FROM information_schema.TABLES t
             JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME
             WHERE t.TABLE_SCHEMA = DATABASE() AND t.TABLE_TYPE = 'BASE TABLE'
             GROUP BY t.TABLE_NAME, t.TABLE_ROWS, t.DATA_LENGTH, t.INDEX_LENGTH",
        ) : []);

        $companies = Company::query()->withTrashed()->orderBy('name')->get(['id', 'name', 'code', 'is_active', 'is_demo', 'deleted_at'])
            ->mapWithKeys(fn (Company $c) => [$c->id => [
                'id' => $c->id, 'name' => $c->name, 'code' => $c->code, 'is_active' => $c->is_active, 'is_demo' => $c->is_demo,
                'archived' => $c->deleted_at !== null, 'rows' => 0, 'db_bytes' => 0, 'file_bytes' => 0, 'tables' => [],
            ]])->all();

        $shared = 0;
        foreach ($tables as $table) {
            if (! $table->tenant) {
                $shared += (int) $table->size_bytes;

                continue;
            }

            // Exact per-company counts use the company_id index; size per row is InnoDB's estimate.
            $perRow = (int) $table->table_rows > 0 ? (int) $table->size_bytes / (int) $table->table_rows : 0;
            $select = $table->has_files ? 'company_id, COUNT(*) AS n, COALESCE(SUM(size_bytes), 0) AS files' : 'company_id, COUNT(*) AS n, 0 AS files';

            try {
                $counts = DB::table($table->name)->selectRaw($select)->whereNotNull('company_id')->groupBy('company_id')->get();
            } catch (Throwable $e) {
                report($e);

                continue;
            }

            foreach ($counts as $row) {
                if (! isset($companies[$row->company_id])) {
                    continue;
                }
                $c = &$companies[$row->company_id];
                $c['rows'] += (int) $row->n;
                $c['db_bytes'] += (int) round($row->n * $perRow);
                $c['file_bytes'] += (int) $row->files;
                $c['tables'][] = ['name' => $table->name, 'rows' => (int) $row->n];
                unset($c);
            }
        }

        $list = array_values(array_map(function (array $c): array {
            usort($c['tables'], fn ($a, $b) => $b['rows'] <=> $a['rows']);
            $c['tables'] = array_slice($c['tables'], 0, 5);

            return $c;
        }, $companies));
        usort($list, fn ($a, $b) => ($b['db_bytes'] + $b['file_bytes']) <=> ($a['db_bytes'] + $a['file_bytes']));

        return ['companies' => $list, 'shared_bytes' => $shared, 'generated_at' => now()->toJSON()];
    }

    /**
     * @return array{driver: string, version: ?string, reachable: bool}
     */
    private function database(): array
    {
        try {
            $connection = DB::connection();
            $connection->getPdo();

            return [
                'driver' => $connection->getDriverName(),
                'version' => (string) $connection->selectOne('SELECT VERSION() AS v')->v,
                'reachable' => true,
            ];
        } catch (Throwable $e) {
            report($e);

            return ['driver' => (string) config('database.default'), 'version' => null, 'reachable' => false];
        }
    }

    /**
     * @return array{disk: string, path_free_bytes: ?int, path_total_bytes: ?int}
     */
    private function storage(): array
    {
        $path = storage_path();
        $free = @disk_free_space($path);
        $total = @disk_total_space($path);

        return [
            'disk' => (string) config('erp.uploads.disk', config('filesystems.default')),
            'path_free_bytes' => $free === false ? null : (int) $free,
            'path_total_bytes' => $total === false ? null : (int) $total,
        ];
    }

    /**
     * Approximate row counts and sizes (InnoDB statistics, not exact counts).
     *
     * @return array<int, array{name: string, rows: int, size_bytes: int}>
     */
    private function tables(): array
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return [];
        }

        try {
            $rows = DB::select(
                'SELECT TABLE_NAME AS name, TABLE_ROWS AS table_rows, (DATA_LENGTH + INDEX_LENGTH) AS size_bytes
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = \'BASE TABLE\'
                 ORDER BY TABLE_NAME',
            );
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        return array_map(fn (object $row) => [
            'name' => (string) $row->name,
            'rows' => (int) $row->table_rows,
            'size_bytes' => (int) $row->size_bytes,
        ], $rows);
    }
}
