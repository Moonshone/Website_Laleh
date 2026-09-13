<?php

declare(strict_types=1);

// Never send database errors or stack traces to public visitors.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');

set_exception_handler(static function (Throwable $exception): void {
    error_log((string) $exception);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo 'An unexpected error occurred. Please try again later.';
});

/**
 * Exceptions of this type contain deliberately written, visitor-safe text.
 * All other exception messages are for the server log only.
 */
class PublicMessageException extends RuntimeException
{
}

/** Send the browser protections used by every PHP entry point. */
function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header_remove('X-Powered-By');
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; form-action 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; img-src 'self' data: blob: https:; media-src 'self' blob: https:; frame-src https:; connect-src 'self'; upgrade-insecure-requests");
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
    header('X-Frame-Options: DENY');
    header('Strict-Transport-Security: max-age=31536000');
}

send_security_headers();

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
