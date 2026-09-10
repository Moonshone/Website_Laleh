<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/admin/bootstrap.php';

$error = '';
$success = false;
$disabled = is_file(__DIR__ . '/.setup-complete');
try {
    if (!$disabled) {
        $disabled = (int) db()->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$disabled) {
        verify_csrf();
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');
        if ($username === '' || preg_match_all('/./us', $username) > 100) {
            throw new RuntimeException('Enter an administrator username of up to 100 characters.');
        }
        if (strlen($password) < 12) {
            throw new RuntimeException('The password must contain at least 12 characters.');
        }
        if (!hash_equals($password, $confirmation)) {
            throw new RuntimeException('The passwords do not match.');
        }
        $pdo = db();
        $pdo->exec('LOCK TABLES admins WRITE');
        try {
            if ((int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() !== 0) {
                $disabled = true;
            } else {
                $statement = $pdo->prepare("INSERT INTO admins (username, password_hash, role) VALUES (:username, :password_hash, 'superadmin')");
                $statement->execute(['username' => $username, 'password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
                $success = true;
                $disabled = true;
            }
        } finally {
            $pdo->exec('UNLOCK TABLES');
        }
        if ($success) {
            @file_put_contents(__DIR__ . '/.setup-complete', "Setup completed. Delete this directory.\n", LOCK_EX);
        }
    }
} catch (RuntimeException $exception) {
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $error = 'Setup could not be completed. Check the private database configuration and imported tables.';
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>First administrator setup — Laleh Barzegar</title><link rel="stylesheet" href="../styles/style.css"></head><body class="admin-page"><main class="admin-shell admin-login"><p class="admin-eyebrow">Laleh Barzegar</p><h1>First administrator setup</h1>
<?php if ($success): ?><p class="admin-message" role="status">Administrator account created successfully.</p><p><strong>Delete the /setup/ directory from the server now.</strong></p>
<?php elseif ($disabled): ?><p class="admin-message">An administrator account already exists. This setup page is disabled.</p>
<?php else: ?><?php if ($error): ?><p class="admin-message admin-error" role="alert"><?= h($error) ?></p><?php endif; ?><form class="admin-form" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><label>Admin username<input name="username" maxlength="100" autocomplete="username" required></label><label>Admin password <span>(at least 12 characters)</span><input type="password" name="password" minlength="12" autocomplete="new-password" required></label><label>Confirm password<input type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required></label><button type="submit">CREATE ADMIN</button></form><?php endif; ?></main></body></html>
