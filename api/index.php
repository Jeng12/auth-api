<?php
header('Content-Type: application/json');
echo json_encode(['status' => 'php_ok', 'env_app_key_set' => !empty(getenv('APP_KEY')), 'env_db_url_set' => !empty(getenv('DB_URL'))]);
