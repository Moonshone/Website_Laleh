<?php

declare(strict_types=1);

/**
 * Return the only client address that the web server has authenticated.
 * Forwarded headers are deliberately ignored unless the server is configured
 * to put the trusted proxy result in REMOTE_ADDR.
 */
function client_ip_address(): string
{
    $address = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return filter_var($address, FILTER_VALIDATE_IP) !== false ? $address : 'unknown';
}

function rate_limit_key(string $value): string
{
    return hash('sha256', $value);
}

/**
 * Build a login limiter key without making either an account or an address a
 * permanent access requirement. The NUL separator prevents ambiguous pairs.
 */
function admin_login_rate_limit_key(string $username, string $ipAddress): string
{
    return strtolower(trim($username)) . "\0" . $ipAddress;
}

/** @return array{allowed: bool, retry_after: int} */
function rate_limit_status(PDO $pdo, string $scope, string $key): array
{
    $statement = $pdo->prepare(
        'SELECT GREATEST(0, UNIX_TIMESTAMP(blocked_until) - UNIX_TIMESTAMP(UTC_TIMESTAMP())) AS retry_after
         FROM rate_limits WHERE scope = :scope AND limiter_key = :limiter_key AND blocked_until > UTC_TIMESTAMP()'
    );
    $statement->execute(['scope' => $scope, 'limiter_key' => rate_limit_key($key)]);
    $retryAfter = $statement->fetchColumn();

    return $retryAfter === false
        ? ['allowed' => true, 'retry_after' => 0]
        : ['allowed' => false, 'retry_after' => max(1, (int) $retryAfter)];
}

/**
 * Atomically count an event in a fixed window and temporarily block its key.
 * The event which reaches the limit is rejected.
 *
 * @return array{allowed: bool, retry_after: int}
 */
function rate_limit_consume(PDO $pdo, string $scope, string $key, int $limit, int $windowSeconds, int $blockSeconds): array
{
    $limiterKey = rate_limit_key($key);
    $pdo->beginTransaction();
    try {
        $select = $pdo->prepare(
            'SELECT attempts, UNIX_TIMESTAMP(window_started_at) AS window_started,
                    UNIX_TIMESTAMP(blocked_until) AS blocked_until
             FROM rate_limits WHERE scope = :scope AND limiter_key = :limiter_key FOR UPDATE'
        );
        $select->execute(['scope' => $scope, 'limiter_key' => $limiterKey]);
        $row = $select->fetch();
        $now = time();

        if ($row && $row['blocked_until'] !== null && (int) $row['blocked_until'] > $now) {
            $pdo->commit();
            return ['allowed' => false, 'retry_after' => max(1, (int) $row['blocked_until'] - $now)];
        }

        // Once a temporary block has elapsed, start clean rather than making
        // the very next typo immediately trigger another block.
        $blockExpired = $row
            && $row['blocked_until'] !== null
            && (int) $row['blocked_until'] <= $now;
        $restartWindow = !$row
            || $blockExpired
            || (int) $row['window_started'] <= $now - $windowSeconds;
        $attempts = !$restartWindow
            ? (int) $row['attempts'] + 1
            : 1;
        $blocked = $attempts >= $limit;

        if ($row) {
            $update = $pdo->prepare(
                'UPDATE rate_limits SET attempts = :attempts,
                    window_started_at = IF(:restart_window = 1, UTC_TIMESTAMP(), window_started_at),
                    blocked_until = IF(:blocked = 1, DATE_ADD(UTC_TIMESTAMP(), INTERVAL :block_seconds SECOND), NULL)
                 WHERE scope = :scope AND limiter_key = :limiter_key'
            );
            $update->execute([
                'attempts' => $attempts,
                'restart_window' => (int) $restartWindow,
                'blocked' => (int) $blocked,
                'block_seconds' => $blockSeconds,
                'scope' => $scope,
                'limiter_key' => $limiterKey,
            ]);
        } else {
            $insert = $pdo->prepare(
                'INSERT INTO rate_limits (scope, limiter_key, attempts, window_started_at, blocked_until)
                 VALUES (:scope, :limiter_key, 1, UTC_TIMESTAMP(), IF(:blocked = 1, DATE_ADD(UTC_TIMESTAMP(), INTERVAL :block_seconds SECOND), NULL))'
            );
            $insert->execute([
                'scope' => $scope,
                'limiter_key' => $limiterKey,
                'blocked' => (int) $blocked,
                'block_seconds' => $blockSeconds,
            ]);
        }
        $pdo->commit();
        return $blocked ? ['allowed' => false, 'retry_after' => $blockSeconds] : ['allowed' => true, 'retry_after' => 0];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function rate_limit_clear(PDO $pdo, string $scope, string $key): void
{
    $statement = $pdo->prepare('DELETE FROM rate_limits WHERE scope = :scope AND limiter_key = :limiter_key');
    $statement->execute(['scope' => $scope, 'limiter_key' => rate_limit_key($key)]);
}

function send_rate_limit_headers(int $retryAfter): void
{
    http_response_code(429);
    header('Retry-After: ' . max(1, $retryAfter));
}
