<?php

define('LARAVEL_START', microtime(true));

// On Vercel, SCRIPT_NAME is /api/index.php. Symfony subtracts its directory
// (/api) when computing the request path, turning /api/me into just 'me'.
// Pretend the entry point is at the root so no prefix gets stripped.
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF']    = '/index.php';

require __DIR__ . '/../vendor/autoload.php';

// Vercel serverless has a read-only filesystem. Create writable dirs in /tmp.
$tmpBootstrapCache = '/tmp/bootstrap/cache';
if (!is_dir($tmpBootstrapCache)) {
    mkdir($tmpBootstrapCache, 0755, true);
}
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

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->useBootstrapPath('/tmp/bootstrap');
$app->useStoragePath($tmpStorage);

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

$response->send();

$kernel->terminate($request, $response);
