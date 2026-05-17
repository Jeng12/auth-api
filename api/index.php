<?php
header('Content-Type: application/json');
echo json_encode([
    'php' => PHP_VERSION,
    'env_file_exists' => file_exists(__DIR__ . '/../.env'),
    'app_key_set' => !empty(getenv('APP_KEY')),
    'db_url_set' => !empty(getenv('DB_URL')),
    'app_env' => getenv('APP_ENV') ?: 'not set',
]);
