<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Safe diagnostics: reports reachability and version, never host, database
 * name, user or password. Failures return a hint, not the driver message.
 */
class HealthController extends ApiController
{
    public function app(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'app' => config('app.name'),
            'environment' => app()->environment(),
            'time' => now()->toJSON(),
        ]);
    }

    public function database(): JsonResponse
    {
        $started = hrtime(true);

        try {
            $connection = DB::connection();
            $connection->getPdo();
            $version = (string) $connection->selectOne('SELECT VERSION() AS v')->v;
            $tls = $this->tlsCipher();

            return response()->json([
                'status' => 'ok',
                'driver' => $connection->getDriverName(),
                'version' => $version,
                'tls' => $tls !== null,
                'latency_ms' => round((hrtime(true) - $started) / 1e6, 1),
                'migrations_table' => $connection->getSchemaBuilder()->hasTable('migrations'),
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => 'error',
                'driver' => config('database.default'),
                'hint' => self::hintFor($e),
            ], 503);
        }
    }

    private function tlsCipher(): ?string
    {
        try {
            $row = DB::selectOne("SHOW SESSION STATUS LIKE 'Ssl_cipher'");

            return $row && $row->Value !== '' ? $row->Value : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** Maps common failures to actionable guidance (also used by `db:check`). */
    public static function hintFor(Throwable $e): string
    {
        $m = $e->getMessage();

        return match (true) {
            str_contains($m, 'could not find driver') => 'PHP pdo_mysql extension is not enabled. See docs/SETUP.md (PHP section).',
            str_contains($m, '[2002]') || str_contains($m, 'Connection refused') || str_contains($m, 'No connection could be made') => 'MySQL is not running or DB_HOST/DB_PORT is wrong. Local: run database/local/start.ps1.',
            str_contains($m, '[1045]') => 'Access denied: check DB_USERNAME / DB_PASSWORD and that the user may connect from this host.',
            str_contains($m, '[1049]') => 'Database does not exist: run database/local/create-app-db.ps1 or CREATE DATABASE.',
            str_contains($m, '[1044]') => 'User lacks privileges on this database: re-run the GRANT statement.',
            str_contains($m, 'SSL') || str_contains($m, 'TLS') || str_contains($m, '[2026]') => 'TLS problem: the provider requires SSL. Set DB_SSL_CA to the downloaded CA certificate path.',
            str_contains($m, '[2006]') || str_contains($m, 'server has gone away') => 'Connection dropped: check network / provider IP allow-list.',
            str_contains($m, 'getaddrinfo') || str_contains($m, 'Name or service not known') => 'DB_HOST could not be resolved: check the hostname.',
            default => 'Database connection failed. Run `php artisan db:check` for details.',
        };
    }
}
