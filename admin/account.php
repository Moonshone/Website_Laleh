<?php

declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_admin();

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmation = (string) ($_POST['new_password_confirmation'] ?? '');
        if (strlen($newPassword) < 12) {
            throw new RuntimeException('The new password must contain at least 12 characters.');
        }
        if (!hash_equals($newPassword, $confirmation)) {
            throw new RuntimeException('The new passwords do not match.');
        }
        $statement = db()->prepare('SELECT password_hash FROM admins WHERE id = :id LIMIT 1');
        $statement->execute(['id' => (int) $_SESSION['admin_id']]);
        $currentHash = $statement->fetchColumn();
        if (!is_string($currentHash) || !password_verify($currentPassword, $currentHash)) {
            throw new RuntimeException('The current password is incorrect.');
        }
        $statement = db()->prepare('UPDATE admins SET password_hash = :password_hash WHERE id = :id');
        $statement->execute([
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'id' => (int) $_SESSION['admin_id'],
        ]);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $success = 'Password changed successfully.';
    } catch (RuntimeException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        $error = 'The password could not be changed. Please try again.';
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Administrator account — Laleh Barzegar</title><link rel="stylesheet" href="../styles/style.css"></head>
<body class="admin-page"><main class="admin-shell"><header class="admin-header"><div><p class="admin-eyebrow">Laleh Barzegar</p><h1>Account</h1><p>Change the password for <?= h((string) $_SESSION['username']) ?>.</p></div><?php admin_navigation(); ?></header>
<?php if ($success): ?><p class="admin-message" role="status"><?= h($success) ?></p><?php endif; ?><?php if ($error): ?><p class="admin-message admin-error" role="alert"><?= h($error) ?></p><?php endif; ?>
<section><h2>Change password</h2><form class="admin-form compact" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><label>Current password<input type="password" name="current_password" autocomplete="current-password" required></label><label>New password <span>(at least 12 characters)</span><input type="password" name="new_password" minlength="12" autocomplete="new-password" required></label><label>Confirm new password<input type="password" name="new_password_confirmation" minlength="12" autocomplete="new-password" required></label><button type="submit">Change password</button></form></section></main></body></html>
