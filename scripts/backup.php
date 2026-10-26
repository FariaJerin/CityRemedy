<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Run this backup from the command line.\n");
}

require_once dirname(__DIR__) . '/app/bootstrap.php';

$stamp = date('Ymd_His');
$backupDirectory = dirname(__DIR__) . '/storage/backups/' . $stamp;
$databaseFile = $backupDirectory . '/database.sql';
$attachmentDirectory = $backupDirectory . '/attachments';
$output = null;

try {
    if (!mkdir($backupDirectory, 0770, true) && !is_dir($backupDirectory)) {
        throw new RuntimeException('Could not create the backup folder. Check storage/backups permissions.');
    }

    $pdo = db();
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->beginTransaction();
    $stream = fopen($databaseFile, 'wb');
    if ($stream === false) {
        throw new RuntimeException('Could not create the database backup file.');
    }
    $write = static function (string $contents) use (&$stream): void {
        $length = strlen($contents);
        for ($offset = 0; $offset < $length;) {
            $written = fwrite($stream, substr($contents, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Could not write the database backup file.');
            }
            $offset += $written;
        }
    };

    $restoreDatabase = 'cityremedy_restore_' . $stamp;
    $write(
        "-- CityRemedy database snapshot created " . date(DATE_ATOM) . "\n"
        . "-- Importing this file creates a separate restore database; it does not overwrite cityremedy.\n"
        . "CREATE DATABASE IF NOT EXISTS `{$restoreDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n"
        . "USE `{$restoreDatabase}`;\n"
        . "SET FOREIGN_KEY_CHECKS=0;\n\n"
    );

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $preferredOrder = [
        'departments',
        'users',
        'complaints',
        'evidence_files',
        'community_votes',
        'verifications',
        'work_orders',
        'resolutions',
        'feedback',
        'notifications',
        'activity_logs',
        'system_settings',
    ];
    $tableRank = array_flip($preferredOrder);
    usort(
        $tables,
        static fn($left, $right): int => ($tableRank[(string)$left] ?? 999)
            <=> ($tableRank[(string)$right] ?? 999)
    );
    foreach ($tables as $tableName) {
        $table = (string)$tableName;
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            throw new RuntimeException('A database table name contains unexpected characters.');
        }
        $quotedTable = '`' . $table . '`';
        $createStatement = $pdo->query('SHOW CREATE TABLE ' . $quotedTable)->fetch(PDO::FETCH_NUM);
        if (!$createStatement || !isset($createStatement[1])) {
            throw new RuntimeException('Could not read the table definition for ' . $table . '.');
        }
        $createSql = preg_replace('/^CREATE TABLE /i', 'CREATE TABLE IF NOT EXISTS ', (string)$createStatement[1], 1);
        $write($createSql . ";\n\n");

        $rows = $pdo->query('SELECT * FROM ' . $quotedTable);
        while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
            $columnNames = array_keys($row);
            $quotedColumns = array_map(
                static fn(string $column): string => '`' . str_replace('`', '``', $column) . '`',
                $columnNames
            );
            $values = [];
            foreach ($row as $value) {
                if ($value === null) {
                    $values[] = 'NULL';
                } else {
                    $quotedValue = $pdo->quote((string)$value);
                    if ($quotedValue === false) {
                        throw new RuntimeException('Could not safely encode a database value.');
                    }
                    $values[] = $quotedValue;
                }
            }
            $write(
                'INSERT INTO ' . $quotedTable . ' (' . implode(', ', $quotedColumns) . ') VALUES ('
                . implode(', ', $values) . ");\n"
            );
        }
        $write("\n");
    }
    $write("SET FOREIGN_KEY_CHECKS=1;\n");
    if (!fclose($stream)) {
        throw new RuntimeException('Could not finish writing the database backup file.');
    }
    $stream = null;
    $pdo->commit();

    if (!is_dir(UPLOAD_DIRECTORY) && !mkdir(UPLOAD_DIRECTORY, 0770, true) && !is_dir(UPLOAD_DIRECTORY)) {
        throw new RuntimeException('Could not access the upload folder while copying attachments.');
    }
    if (is_dir(UPLOAD_DIRECTORY)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(UPLOAD_DIRECTORY, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $entry) {
            if (!$entry->isFile()) {
                continue;
            }
            $relative = substr($entry->getPathname(), strlen(UPLOAD_DIRECTORY) + 1);
            $destination = $attachmentDirectory . DIRECTORY_SEPARATOR . $relative;
            $parent = dirname($destination);
            if (!is_dir($parent) && !mkdir($parent, 0770, true) && !is_dir($parent)) {
                throw new RuntimeException('Could not create an attachment backup folder.');
            }
            if (!copy($entry->getPathname(), $destination)) {
                throw new RuntimeException('Could not copy an uploaded attachment into the backup.');
            }
        }
    }

    $output = "Backup complete: {$backupDirectory}\n";
} catch (Throwable $error) {
    if (isset($stream) && is_resource($stream)) {
        fclose($stream);
    }
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[CityRemedy backup] ' . $error->getMessage());
    fwrite(STDERR, "Backup failed: " . $error->getMessage() . "\n");
    exit(1);
}

fwrite(STDOUT, (string)$output);
