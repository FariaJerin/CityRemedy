<?php
declare(strict_types=1);

// Ensure Composer vendor autoloader is active
if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}

$localConfig = dirname(__DIR__) . '/config.local.php';
$config = is_file($localConfig)
    ? require $localConfig
    : require dirname(__DIR__) . '/config.example.php';

define('APP_NAME', (string)($config['app_name'] ?? 'CityRemedy'));
define('APP_BASE_URL', rtrim((string)($config['base_url'] ?? '/CityRemedy/public'), '/'));
define('UPLOAD_MAX_BYTES', (int)($config['upload_max_bytes'] ?? (8 * 1024 * 1024)));
define('UPLOAD_DIRECTORY', dirname(__DIR__) . '/storage/uploads');

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    $isSecure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $isSecure,
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/services.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    global $config;

    // Check if running under PHPUnit environment variables
    $name = getenv('DB_NAME') ?: (string)($config['db_name'] ?? 'cityremedy');
    $host = getenv('DB_HOST') ?: (string)($config['db_host'] ?? '127.0.0.1');
    $port = (string)($config['db_port'] ?? '3306');
    $user = getenv('DB_USER') ?: (string)($config['db_user'] ?? 'root');
    $pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : (string)($config['db_pass'] ?? '');

    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    return $pdo;
}


