<?php

declare(strict_types=1);

// Never send database errors or stack traces to public visitors.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

function private_config(): array
{
    static $local;
    if (is_array($local)) {
        return $local;
    }
    $privateConfig = __DIR__ . '/config.local.php';
    $local = is_file($privateConfig) ? require $privateConfig : [];
    return is_array($local) ? $local : [];
}

function app_setting(string $name, string $default = ''): string
{
    $environmentValue = getenv($name);
    if ($environmentValue !== false && $environmentValue !== '') {
        return $environmentValue;
    }
    return (string) (private_config()[$name] ?? $default);
}

function database_config(): array
{
    static $databaseConfig;
    if (is_array($databaseConfig)) {
        return $databaseConfig;
    }

    $privateConfig = __DIR__ . '/config/database.local.php';
    $databaseConfig = is_file($privateConfig) ? require $privateConfig : [];

    return is_array($databaseConfig) ? $databaseConfig : [];
}

function db(): PDO
{
    static $connection;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $local = database_config();
    $host = (string) ($local['host'] ?? 'mysql.lalehbarzegar.com');
    $name = (string) ($local['dbname'] ?? 'neweshtaniha');
    $user = (string) ($local['user'] ?? 'laleh');
    $password = (string) ($local['password'] ?? '');

    if ($password === '') {
        throw new RuntimeException('DB_PASSWORD is not configured.');
    }
    $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";

    $connection = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $connection;
}
