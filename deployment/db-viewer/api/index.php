<?php

/*
 * Online database viewer for the hosted demo (Adminer, MySQL only) on Vercel.
 * Two locks: HTTP Basic auth (DBVIEW_USER / DBVIEW_PASSWORD env vars) in front,
 * then the MySQL login itself — and Adminer may only connect to DBVIEW_SERVER.
 */

$user = (string) getenv('DBVIEW_USER');
$pass = (string) getenv('DBVIEW_PASSWORD');
if ($user === '' || strlen($pass) < 16 || ! getenv('DBVIEW_SERVER')) {
    http_response_code(503);
    exit('Database viewer is not configured.');
}

$givenUser = $_SERVER['PHP_AUTH_USER'] ?? '';
$givenPass = $_SERVER['PHP_AUTH_PW'] ?? '';
$header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if ($givenUser === '' && stripos($header, 'basic ') === 0) {
    [$givenUser, $givenPass] = array_pad(explode(':', (string) base64_decode(substr($header, 6)), 2), 2, '');
}

if (! hash_equals($user, $givenUser) || ! hash_equals($pass, $givenPass)) {
    usleep(random_int(300000, 700000)); // slow down guessing
    header('WWW-Authenticate: Basic realm="ERP database viewer", charset="UTF-8"');
    http_response_code(401);
    exit('Login required.');
}

header('X-Robots-Tag: noindex, nofollow');
ini_set('session.save_path', sys_get_temp_dir());

// Adminer reads adminer-plugins.php and adminer-plugins/ relative to the working directory.
chdir(__DIR__);
require __DIR__.'/adminer.php';
