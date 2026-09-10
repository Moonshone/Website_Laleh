<?php

declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if ($username !== '' && $password !== '') {
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
                header('Location: dashboard.php');
                exit;
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
        }
    }
    $error = 'Invalid username or password.';
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>News administration — Laleh Barzegar</title><link rel="stylesheet" href="../styles/style.css"></head>
<body class="admin-page"><main class="admin-shell admin-login"><p class="admin-eyebrow">Laleh Barzegar</p><h1>News administration</h1>
<?php if ($error): ?><p class="admin-message admin-error" role="alert"><?= h($error) ?></p><?php endif; ?>
<form class="admin-form" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
<label>Username<input name="username" autocomplete="username" required></label><label>Password<input type="password" name="password" autocomplete="current-password" required></label><button type="submit">LOG IN</button></form><p><a class="admin-primary-link" href="/admin/forgot-password.php">Forgot password?</a></p></main></body></html>
