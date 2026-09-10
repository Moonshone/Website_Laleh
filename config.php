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

function db(): PDO
{
    static $connection;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $local = private_config();

    $host = getenv('DB_HOST') ?: ($local['DB_HOST'] ?? '');
    $port = getenv('DB_PORT') ?: ($local['DB_PORT'] ?? '3306');
    $name = getenv('DB_NAME') ?: ($local['DB_NAME'] ?? 'neweshtaniha');
    $user = getenv('DB_USER') ?: ($local['DB_USER'] ?? 'laleh');
    $password = getenv('DB_PASSWORD') ?: ($local['DB_PASSWORD'] ?? '');
    if ($host === '' || $password === '') {
        throw new RuntimeException('Database configuration is incomplete.');
    }
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    $connection = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $connection;
}
