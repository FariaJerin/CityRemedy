<?php
declare(strict_types=1);

// 1. Set environment variables for test execution
putenv('DB_NAME=cityremedy_test');

if (getenv('GITHUB_ACTIONS') === 'true') {
    putenv('DB_HOST=127.0.0.1');
    putenv('DB_PORT=3306');
    putenv('DB_USER=root');
    putenv('DB_PASS=');
}

// 2. Autoload dependencies
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// 3. Auto-seed schema in GitHub Actions CI environment
if (getenv('GITHUB_ACTIONS') === 'true') {
    try {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
        $dbname = 'cityremedy_test';

        $pdo = new PDO("mysql:host={$host};port={$port}", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => true
        ]);

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}`");
        $pdo->exec("USE `{$dbname}`");

        $schemaFile = __DIR__ . '/../database/schema.sql';
        if (file_exists($schemaFile)) {
            $sql = file_get_contents($schemaFile);
            $pdo->exec($sql);
        }
    } catch (\Throwable $e) {
        error_log("CI DB Seed Warning: " . $e->getMessage());
    }
}

// 4. Load application bootstrap
require_once __DIR__ . '/../app/bootstrap.php';

