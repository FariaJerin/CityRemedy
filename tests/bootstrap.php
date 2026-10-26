<?php
declare(strict_types=1);

// Set test environment database name
putenv('DB_NAME=cityremedy_test');

if (getenv('GITHUB_ACTIONS') === 'true') {
    putenv('DB_HOST=127.0.0.1');
    putenv('DB_PORT=3306');
    putenv('DB_USER=root');
    putenv('DB_PASS=');
}

// Autoload dependencies
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Load application bootstrap
require_once __DIR__ . '/../app/bootstrap.php';

