<?php

define('LARAVEL_START', microtime(true));

require __DIR__ . '/../vendor/autoload.php';

// Vercel serverless has a read-only filesystem.
// Create writable directories in /tmp and redirect Laravel to them.
$tmpBootstrapCache = '/tmp/bootstrap/cache';
if (!is_dir($tmpBootstrapCache)) {
    mkdir($tmpBootstrapCache, 0755, true);
}
// Seed /tmp with any pre-generated package manifests from the bundle.
foreach (glob(__DIR__ . '/../bootstrap/cache/*.php') as $f) {
    $dest = $tmpBootstrapCache . '/' . basename($f);
    if (!file_exists($dest)) {
        copy($f, $dest);
    }
}

$tmpStorage = '/tmp/storage';
foreach (['logs', 'framework/cache/data', 'framework/sessions', 'framework/views'] as $sub) {
    $dir = "$tmpStorage/$sub";
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Vercel routes all traffic to api/index.php, so SCRIPT_NAME is
// /api/index.php. Symfony's request parser strips the /api/ directory
// prefix from REQUEST_URI, making Laravel see /me instead of /api/me.
// Override to an empty base so the full URI is used as the path.
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF']    = '/index.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->useBootstrapPath('/tmp/bootstrap');
$app->useStoragePath($tmpStorage);

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

$response->send();

$kernel->terminate($request, $response);
