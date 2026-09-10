<?php

declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$token = (string) ($_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['token'] ?? '') : ($_GET['token'] ?? ''));
$validFormat = preg_match('/\A[a-f0-9]{64}\z/', $token) === 1;
$error = '';
$success = false;
$tokenIsValid = false;

try {
    if ($validFormat) {
        $statement = db()->prepare('SELECT id FROM password_resets WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at > UTC_TIMESTAMP() LIMIT 1');
        $statement->execute(['token_hash' => hash('sha256', $token)]);
        $tokenIsValid = $statement->fetchColumn() !== false;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmation = (string) ($_POST['new_password_confirmation'] ?? '');
        if (!$tokenIsValid) {
            throw new RuntimeException('This password reset link is invalid, expired, or has already been used.');
        }
        if (strlen($newPassword) < 12) {
            throw new RuntimeException('The new password must contain at least 12 characters.');
        }
        if (!hash_equals($newPassword, $confirmation)) {
            throw new RuntimeException('The new passwords do not match.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        $reset = $pdo->prepare('SELECT id, admin_id FROM password_resets WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at > UTC_TIMESTAMP() FOR UPDATE');
        $reset->execute(['token_hash' => hash('sha256', $token)]);
        $resetRow = $reset->fetch();
        if (!$resetRow) {
            throw new RuntimeException('This password reset link is invalid, expired, or has already been used.');
        }
        $update = $pdo->prepare('UPDATE admins SET password_hash = :password_hash WHERE id = :id');
        $update->execute(['password_hash' => password_hash($newPassword, PASSWORD_DEFAULT), 'id' => (int) $resetRow['admin_id']]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('This administrator account is no longer available.');
        }
        $invalidate = $pdo->prepare('UPDATE password_resets SET used_at = UTC_TIMESTAMP() WHERE admin_id = :admin_id AND used_at IS NULL');
        $invalidate->execute(['admin_id' => (int) $resetRow['admin_id']]);
        $pdo->commit();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $success = true;
        $tokenIsValid = false;
    }
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
if (!$success && !$error && !$tokenIsValid) {
    $error = 'This password reset link is invalid, expired, or has already been used.';
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Reset administrator password — Laleh Barzegar</title><link rel="stylesheet" href="../styles/style.css"></head>
<body class="admin-page"><main class="admin-shell admin-login"><p class="admin-eyebrow">Laleh Barzegar</p><h1>Reset password</h1>
<?php if ($success): ?><p class="admin-message" role="status">Your password has been reset successfully.</p>
<?php elseif ($error): ?><p class="admin-message admin-error" role="alert"><?= h($error) ?></p><?php endif; ?>
<?php if ($tokenIsValid): ?><form class="admin-form" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="token" value="<?= h($token) ?>"><label>New password <span>(at least 12 characters)</span><input type="password" name="new_password" minlength="12" autocomplete="new-password" required></label><label>Confirm new password<input type="password" name="new_password_confirmation" minlength="12" autocomplete="new-password" required></label><button type="submit">Reset password</button></form><?php endif; ?>
<p><a class="admin-primary-link" href="/admin/login.php">Back to admin login</a></p></main></body></html>
