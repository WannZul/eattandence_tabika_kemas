<?php
declare(strict_types=1);

require_once __DIR__ . '/errors.php';
require_once __DIR__ . '/config.php';

function db(): mysqli
{
    static $connection = null;
    if ($connection instanceof mysqli) {
        return $connection;
    }

    $host = env_value('DB_HOST', '127.0.0.1') ?? '127.0.0.1';
    $port = (int) (env_value('DB_PORT', '3306') ?? '3306');
    $name = env_value('DB_NAME', 'face_attendance') ?? 'face_attendance';
    $user = env_value('DB_USER', 'root') ?? 'root';
    $password = env_value('DB_PASSWORD', '') ?? '';

    if (app_env() === 'production' && ($password === '' || strtolower($user) === 'root')) {
        error_log('Unsafe production database configuration refused.');
        throw new RuntimeException('Konfigurasi pangkalan data production belum selamat.');
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $connection = new mysqli($host, $user, $password, $name, $port);
        $connection->set_charset('utf8mb4');
        $connection->query("SET time_zone = '+08:00'");
    } catch (mysqli_sql_exception $exception) {
        error_log('Database connection failed: ' . $exception->getMessage());
        throw new UserFacingException('Sistem tidak dapat menyambung ke pangkalan data. Sila hubungi pentadbir.', 503);
    }

    return $connection;
}
