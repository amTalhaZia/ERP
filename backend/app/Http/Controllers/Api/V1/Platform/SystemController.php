<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
