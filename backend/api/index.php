<?php

/*
 * Vercel serverless entry point (vercel-php runtime) for the hosted demo.
 * The deployed code is read-only, so everything Laravel writes goes to /tmp.
 * Production servers use public/index.php directly (see deployment/).
 */

$set = static function (string $key, string $value): void {
    putenv("{$key}={$value}");
    $_ENV[$key] = $_SERVER[$key] = $value;
};

$storage = '/tmp/storage';
foreach (['app/public', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs', 'certs'] as $dir) {
    if (! is_dir("{$storage}/{$dir}")) {
        @mkdir("{$storage}/{$dir}", 0775, true);
    }
}

$set('LARAVEL_STORAGE_PATH', $storage);
$set('VIEW_COMPILED_PATH', "{$storage}/framework/views");
foreach (['APP_SERVICES_CACHE' => 'services', 'APP_PACKAGES_CACHE' => 'packages', 'APP_CONFIG_CACHE' => 'config', 'APP_ROUTES_CACHE' => 'routes', 'APP_EVENTS_CACHE' => 'events'] as $key => $file) {
    $set($key, "/tmp/{$file}.php");
}

// Hosted MySQL (Aiven) CA certificate arrives as text in DB_SSL_CA_PEM.
if (($pem = getenv('DB_SSL_CA_PEM')) && ! getenv('DB_SSL_CA')) {
    $ca = "{$storage}/certs/ca.pem";
    if (! is_file($ca)) {
        file_put_contents($ca, $pem);
    }
    $set('DB_SSL_CA', $ca);
}

// This file lives in /api, but the app is served from the site root: without this, the
// request is treated as based at "/api" and "/api/v1/..." routes lose their "/api" prefix.
$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__.'/../public/index.php';

require __DIR__.'/../public/index.php';
