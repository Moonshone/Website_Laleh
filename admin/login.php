<?php

declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

const ADMIN_LOGIN_FAILED_ATTEMPTS = 10;
const ADMIN_LOGIN_WINDOW_SECONDS = 600;
const ADMIN_LOGIN_THROTTLE_SECONDS = 300;

if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $normalizedUsername = strtolower($username);
    $password = (string) ($_POST['password'] ?? '');
    $ipAddress = client_ip_address();
    $loginLimitKey = admin_login_rate_limit_key($normalizedUsername, $ipAddress);
    $limited = false;
    try {
        $pdo = db();
        $loginLimit = rate_limit_status($pdo, 'admin_login_username_ip', $loginLimitKey);
        if (!$loginLimit['allowed']) {
            send_rate_limit_headers($loginLimit['retry_after']);
            $error = 'Too many login attempts. Please try again later.';
            $limited = true;
        }
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
    }
    if (!$limited && $username !== '' && $password !== '') {
        try {
            $statement = db()->prepare('SELECT id, username, password_hash, role FROM admins WHERE username = :username LIMIT 1');
            $statement->execute(['username' => $username]);
            $admin = $statement->fetch();
            if ($admin && password_verify($password, $admin['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = (int) $admin['id'];
                $_SESSION['username'] = (string) $admin['username'];
                $_SESSION['role'] = (string) $admin['role'];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                rate_limit_clear($pdo, 'admin_login_username_ip', $loginLimitKey);
                header('Location: dashboard.php');
                exit;
            }
        } catch (RuntimeException $exception) {
            error_log($exception->getMessage());
            if ($exception->getMessage() === 'DB_PASSWORD is not configured.') {
                $error = 'DB_PASSWORD is not configured.';
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
        }
    }
    if (!$limited && $error === '') {
        try {
            $pdo = db();
            // Limit only this normalized username/address pair. Shared hotel,
            // mobile, VPN, and public-Wi-Fi addresses remain valid login paths
            // for other accounts, and no address is permanently denied.
            // rate_limit_consume rejects the event which reaches its limit.
            // Use one more than the allowed failure count so ten failed
            // logins remain possible before the five-minute throttle starts.
            $loginLimit = rate_limit_consume(
                $pdo,
                'admin_login_username_ip',
                $loginLimitKey,
                ADMIN_LOGIN_FAILED_ATTEMPTS + 1,
                ADMIN_LOGIN_WINDOW_SECONDS,
                ADMIN_LOGIN_THROTTLE_SECONDS
            );
            if (!$loginLimit['allowed']) {
                send_rate_limit_headers($loginLimit['retry_after']);
                $error = 'Too many login attempts. Please try again later.';
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
        }
    }
    if (!$limited && $error === '') {
        $error = 'Invalid username or password.';
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>News administration — Laleh Barzegar</title><link rel="stylesheet" href="../styles/style.css"></head>
<body class="admin-page"><main class="admin-shell admin-login"><p class="admin-eyebrow">Laleh Barzegar</p><h1>News administration</h1>
<?php if ($error): ?><p class="admin-message admin-error" role="alert"><?= h($error) ?></p><?php endif; ?>
<form class="admin-form" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
<label>Username<input name="username" autocomplete="username" required></label><label>Password<input type="password" name="password" autocomplete="current-password" required></label><button type="submit">LOG IN</button></form><p><a class="admin-primary-link" href="/admin/forgot-password.php">Forgot password?</a></p></main></body></html>
