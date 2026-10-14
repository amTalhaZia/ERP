<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class DbCheckCommand extends Command
{
    protected $signature = 'db:check';

    protected $description = 'Verify the database connection and print safe diagnostics with troubleshooting hints';

    public function handle(): int
    {
        $config = config('database.connections.'.config('database.default'));

        $this->components->twoColumnDetail('Connection', config('database.default'));
        $this->components->twoColumnDetail('Host', $config['host'].':'.$config['port']);
        $this->components->twoColumnDetail('Database', $config['database']);
        $this->components->twoColumnDetail('User', $config['username']);
        $this->components->twoColumnDetail('TLS CA', isset($config['options'][\PDO::MYSQL_ATTR_SSL_CA]) ? 'configured' : 'not configured');

        $started = hrtime(true);

        try {
            DB::connection()->getPdo();
            $version = DB::selectOne('SELECT VERSION() AS v')->v;
            $ms = round((hrtime(true) - $started) / 1e6, 1);
            $cipher = $this->cipher();

            $this->components->twoColumnDetail('Server version', $version);
            $this->components->twoColumnDetail('TLS cipher', $cipher ?? 'none (plaintext)');
            $this->components->twoColumnDetail('Latency', "{$ms} ms");
            $this->components->twoColumnDetail('Tables', (string) count(DB::connection()->getSchemaBuilder()->getTableListing(DB::connection()->getDatabaseName(), false)));
            $this->components->info('Database connection OK.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->components->error('Database connection FAILED.');
            $this->components->twoColumnDetail('Hint', HealthController::hintFor($e));
            $this->components->twoColumnDetail('Driver message', $this->redact($e->getMessage(), $config['password'] ?? ''));

            return self::FAILURE;
        }
    }

    private function cipher(): ?string
    {
        try {
            $row = DB::selectOne("SHOW SESSION STATUS LIKE 'Ssl_cipher'");

            return $row && $row->Value !== '' ? $row->Value : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function redact(string $message, string $password): string
    {
        return $password === '' ? $message : str_replace($password, '********', $message);
    }
}
