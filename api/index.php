<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

set_error_handler(function($severity, $message, $file, $line) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => $message, 'file' => $file, 'line' => $line]);
    exit;
});

try {
    if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'vendor/autoload.php not found', 'dir' => __DIR__]);
        exit;
    }

    define('LARAVEL_START', microtime(true));
    require __DIR__ . '/../vendor/autoload.php';
    $app = require_once __DIR__ . '/../bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $response = $kernel->handle($request = Illuminate\Http\Request::capture());
    $response->send();
    $kernel->terminate($request, $response);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error'   => $e->getMessage(),
        'class'   => get_class($e),
        'file'    => $e->getFile(),
        'line'    => $e->getLine(),
    ]);
}
