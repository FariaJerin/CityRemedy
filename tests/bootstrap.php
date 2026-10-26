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

/**
 * Safety net: make sure the `cityremedy_test` database has the application
 * tables before any test runs. It never touches the real `cityremedy` database.
 *
 * database/schema.sql targets the real database (CREATE DATABASE cityremedy; USE cityremedy;),
 * so those two statements are removed and everything is created in cityremedy_test.
 * Statements are idempotent (IF NOT EXISTS / INSERT IGNORE), so running this
 * repeatedly, or on a partly-created database, is harmless.
 *
 * If MySQL is not reachable, this silently does nothing so the pure unit tests
 * can still run.
 */
function ensure_test_database_schema(): void
{
    global $config;

    $testDatabase = 'cityremedy_test';

    try {
        $host = (string)($config['db_host'] ?? '127.0.0.1');
        $port = (string)($config['db_port'] ?? '3306');
        $user = (string)($config['db_user'] ?? 'root');
        $pass = (string)($config['db_pass'] ?? '');

        $pdo = new PDO(
            "mysql:host={$host};port={$port};charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$testDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$testDatabase}`");

        $required = ['departments', 'users', 'complaints', 'system_settings'];
        $missing = false;
        foreach ($required as $table) {
            if ($pdo->query("SHOW TABLES LIKE '{$table}'")->fetchColumn() === false) {
                $missing = true;
                break;
            }
        }
        if (!$missing) {
            return;
        }

        $sql = (string)file_get_contents(__DIR__ . '/../database/schema.sql');
        $sql = preg_replace('/^\s*CREATE\s+DATABASE\b[^;]*;/im', '', $sql);
        $sql = preg_replace('/^\s*USE\s+[^;]+;/im', '', $sql);
        $sql = preg_replace('/\bCREATE\s+TABLE\s+(?!IF\s+NOT\s+EXISTS)/i', 'CREATE TABLE IF NOT EXISTS ', $sql);
        $sql = preg_replace('/\bINSERT\s+INTO\b/i', 'INSERT IGNORE INTO', $sql);

        foreach (explode(';', $sql) as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }
    } catch (Throwable $e) {
        fwrite(STDERR, '[tests/bootstrap] Could not prepare test database: ' . $e->getMessage() . PHP_EOL);
    }
}

ensure_test_database_schema();


