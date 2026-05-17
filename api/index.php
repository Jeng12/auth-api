<?php
define('LARAVEL_START', microtime(true));
require __DIR__ . '/../vendor/autoload.php';

$tmpBootstrapCache = '/tmp/bootstrap/cache';
if (!is_dir($tmpBootstrapCache)) mkdir($tmpBootstrapCache, 0755, true);
foreach (glob(__DIR__ . '/../bootstrap/cache/*.php') as $f) {
    $dest = $tmpBootstrapCache . '/' . basename($f);
    if (!file_exists($dest)) copy($f, $dest);
}
$tmpStorage = '/tmp/storage';
foreach (['logs', 'framework/cache/data', 'framework/sessions', 'framework/views'] as $sub) {
    $dir = "$tmpStorage/$sub";
    if (!is_dir($dir)) mkdir($dir, 0755, true);
}

try {
    $app = require_once __DIR__ . '/../bootstrap/app.php';
    $app->useBootstrapPath('/tmp/bootstrap');
    $app->useStoragePath($tmpStorage);

    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $kernel->bootstrap();

    $request = Illuminate\Http\Request::capture();

    $router = $app->make(Illuminate\Routing\Router::class);
    $routes = [];
    foreach ($router->getRoutes() as $route) {
        $routes[] = implode('|', $route->methods()) . ' ' . $route->uri();
    }

    header('Content-Type: application/json');
    echo json_encode([
        'REQUEST_URI'   => $_SERVER['REQUEST_URI'] ?? null,
        'PATH_INFO'     => $_SERVER['PATH_INFO'] ?? null,
        'SCRIPT_NAME'   => $_SERVER['SCRIPT_NAME'] ?? null,
        'laravel_path'  => $request->path(),
        'laravel_url'   => $request->url(),
        'route_count'   => count($routes),
        'routes'        => $routes,
    ]);
} catch (\Throwable $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
}
