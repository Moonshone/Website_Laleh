<?php

declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: account.php');
    exit;
}

$submitted = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $submitted = true;
    if (valid_admin_email($email)) {
        try {
            $statement = db()->prepare('SELECT id, email FROM admins WHERE email = :email LIMIT 1');
            $statement->execute(['email' => $email]);
            $admin = $statement->fetch();
            if ($admin) {
                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $pdo = db();
                $pdo->beginTransaction();
                $insert = $pdo->prepare('INSERT INTO password_resets (admin_id, token_hash, expires_at) VALUES (:admin_id, :token_hash, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 MINUTE))');
                $insert->execute(['admin_id' => (int) $admin['id'], 'token_hash' => $tokenHash]);
                $pdo->commit();
                if (!send_password_reset_email((string) $admin['email'], $token)) {
                    error_log('Password reset email delivery failed for admin ID ' . (int) $admin['id']);
                }
            }
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($exception->getMessage());
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Forgot administrator password — Laleh Barzegar</title><link rel="stylesheet" href="../styles/style.css"></head>
<body class="admin-page"><main class="admin-shell admin-login"><p class="admin-eyebrow">Laleh Barzegar</p><h1>Forgot password?</h1>
<?php if ($submitted): ?><p class="admin-message" role="status">If an administrator account exists for this email address, a password reset link has been sent.</p>
<?php else: ?><p>Enter the email address for your administrator account.</p><form class="admin-form" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><label>Email address<input type="email" name="email" maxlength="255" autocomplete="email" required></label><button type="submit">Send reset link</button></form><?php endif; ?>
<p><a class="admin-primary-link" href="/admin/login.php">Back to admin login</a></p></main></body></html>
