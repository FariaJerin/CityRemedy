<?php
declare(strict_types=1);

// 1. Tell the app to use the test database
putenv('DB_NAME=cityremedy_test');

// 2. Include composer autoloader if present
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// 3. Include your app bootstrap
require_once __DIR__ . '/../app/bootstrap.php';

