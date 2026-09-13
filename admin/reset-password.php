<?php

declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

const PASSWORD_RESET_SESSION_LIFETIME_SECONDS = 30 * 60;

header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$error = '';
$success = false;
$resetIsValid = false;
$now = time();

if (isset($_GET['token'])) {
    // A new link always replaces any previous reset state in this browser.
    unset($_SESSION['password_reset']);
    $token = (string) $_GET['token'];
    $validFormat = preg_match('/\A[a-f0-9]{64}\z/', $token) === 1;

    try {
        if (!$validFormat) {
            throw new RuntimeException('This password reset link is invalid, expired, or has already been used.');
        }

        $pdo = db();
        $pdo->beginTransaction();
        $statement = $pdo->prepare(
            'SELECT id, admin_id, TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), expires_at) AS remaining_seconds'
            . ' FROM password_resets'
            . ' WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at > UTC_TIMESTAMP()'
            . ' LIMIT 1 FOR UPDATE'
        );
        $statement->execute(['token_hash' => hash('sha256', $token)]);
        $reset = $statement->fetch();
        if (!$reset) {
            throw new RuntimeException('This password reset link is invalid, expired, or has already been used.');
        }

        // Claim the database token while holding its row lock. The raw token is
        // discarded and only this browser's server-side session can continue.
        $claim = $pdo->prepare('UPDATE password_resets SET used_at = UTC_TIMESTAMP() WHERE id = :id AND used_at IS NULL');
        $claim->execute(['id' => (int) $reset['id']]);
        if ($claim->rowCount() !== 1) {
            throw new RuntimeException('This password reset link is invalid, expired, or has already been used.');
        }
        $pdo->commit();

        $remainingSeconds = max(0, min(PASSWORD_RESET_SESSION_LIFETIME_SECONDS, (int) $reset['remaining_seconds']));
        $_SESSION['password_reset'] = [
            'reset_id' => (int) $reset['id'],
            'admin_id' => (int) $reset['admin_id'],
            'expires_at' => $now + $remainingSeconds,
        ];
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        header('Location: /admin/reset-password.php', true, 303);
        exit;
    } catch (RuntimeException $exception) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($exception->getMessage());
        $error = 'The password could not be reset. Please request a new reset link.';
    } finally {
        // Do not retain the credential beyond this first request.
        unset($token);
    }
} else {
    $resetState = $_SESSION['password_reset'] ?? null;
    if (is_array($resetState)
        && isset($resetState['reset_id'], $resetState['admin_id'], $resetState['expires_at'])
        && (int) $resetState['expires_at'] > $now) {
        $resetIsValid = true;
    } else {
        unset($_SESSION['password_reset']);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            verify_csrf();
            if (!$resetIsValid) {
                throw new RuntimeException('This password reset link is invalid, expired, or has already been used.');
            }
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $confirmation = (string) ($_POST['new_password_confirmation'] ?? '');
            if (strlen($newPassword) < 12) {
                throw new RuntimeException('The new password must contain at least 12 characters.');
            }
            if (!hash_equals($newPassword, $confirmation)) {
                throw new RuntimeException('The new passwords do not match.');
            }

            $pdo = db();
            $pdo->beginTransaction();
            $reset = $pdo->prepare(
                'SELECT id FROM password_resets'
                . ' WHERE id = :id AND admin_id = :admin_id AND used_at IS NOT NULL AND expires_at > UTC_TIMESTAMP()'
                . ' FOR UPDATE'
            );
            $reset->execute([
                'id' => (int) $resetState['reset_id'],
                'admin_id' => (int) $resetState['admin_id'],
            ]);
            if (!$reset->fetch()) {
                throw new RuntimeException('This password reset link is invalid, expired, or has already been used.');
            }
            $update = $pdo->prepare('UPDATE admins SET password_hash = :password_hash, session_version = session_version + 1 WHERE id = :id');
            $update->execute([
                'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                'id' => (int) $resetState['admin_id'],
            ]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('This administrator account is no longer available.');
            }
            // Deletion invalidates this claimed row and every other reset link
            // for the account, including concurrent reset attempts.
            $invalidate = $pdo->prepare('DELETE FROM password_resets WHERE admin_id = :admin_id');
            $invalidate->execute(['admin_id' => (int) $resetState['admin_id']]);
            $pdo->commit();

            unset($_SESSION['password_reset']);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $success = true;
            $resetIsValid = false;
        } catch (RuntimeException $exception) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($exception->getMessage());
            $error = 'The password could not be reset. Please request a new reset link.';
        }
    }
}

if (!$success && !$error && !$resetIsValid) {
    $error = 'This password reset link is invalid, expired, or has already been used.';
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Reset administrator password — Laleh Barzegar</title><link rel="stylesheet" href="../styles/style.css"></head>
<body class="admin-page"><main class="admin-shell admin-login"><p class="admin-eyebrow">Laleh Barzegar</p><h1>Reset password</h1>
<?php if ($success): ?><p class="admin-message" role="status">Your password has been reset successfully.</p>
<?php elseif ($error): ?><p class="admin-message admin-error" role="alert"><?= h($error) ?></p><?php endif; ?>
<?php if ($resetIsValid): ?><form class="admin-form" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><label>New password <span>(at least 12 characters)</span><input type="password" name="new_password" minlength="12" autocomplete="new-password" required></label><label>Confirm new password<input type="password" name="new_password_confirmation" minlength="12" autocomplete="new-password" required></label><button type="submit">Reset password</button></form><?php endif; ?>
<p><a class="admin-primary-link" href="/admin/login.php">Back to admin login</a></p></main></body></html>
