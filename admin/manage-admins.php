<?php

declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_superadmin();

$error = '';
$flash = (string) ($_SESSION['flash'] ?? '');
unset($_SESSION['flash']);

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = (string) ($_POST['action'] ?? '');
        $targetId = filter_var($_POST['admin_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($action === 'create') {
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $confirmation = (string) ($_POST['password_confirmation'] ?? '');
            $role = (string) ($_POST['role'] ?? '');
            if ($username === '' || preg_match_all('/./us', $username) > 100) {
                throw new RuntimeException('Enter a username of up to 100 characters.');
            }
            if (strlen($password) < 12) {
                throw new RuntimeException('The password must contain at least 12 characters.');
            }
            if (!hash_equals($password, $confirmation)) {
                throw new RuntimeException('The passwords do not match.');
            }
            if (!in_array($role, ['admin', 'superadmin'], true)) {
                throw new RuntimeException('Choose a valid role.');
            }
            $statement = db()->prepare('SELECT COUNT(*) FROM admins WHERE username = :username');
            $statement->execute(['username' => $username]);
            if ((int) $statement->fetchColumn() > 0) {
                throw new RuntimeException('That username is already in use.');
            }
            $statement = db()->prepare('INSERT INTO admins (username, password_hash, role) VALUES (:username, :password_hash, :role)');
            $statement->execute(['username' => $username, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'role' => $role]);
            $_SESSION['flash'] = 'The administrator was created.';
        } elseif ($action === 'password' && $targetId) {
            $password = (string) ($_POST['new_password'] ?? '');
            if (strlen($password) < 12) {
                throw new RuntimeException('The new password must contain at least 12 characters.');
            }
            if (!hash_equals($password, (string) ($_POST['new_password_confirmation'] ?? ''))) {
                throw new RuntimeException('The new passwords do not match.');
            }
            $statement = db()->prepare('UPDATE admins SET password_hash = :password_hash WHERE id = :id');
            $statement->execute(['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $targetId]);
            if ($statement->rowCount() !== 1) {
                throw new RuntimeException('The administrator was not found.');
            }
            $_SESSION['flash'] = 'The password was changed.';
        } elseif ($action === 'role' && $targetId) {
            $role = (string) ($_POST['role'] ?? '');
            if (!in_array($role, ['admin', 'superadmin'], true)) {
                throw new RuntimeException('Choose a valid role.');
            }
            $pdo = db();
            $pdo->beginTransaction();
            $target = $pdo->prepare('SELECT role FROM admins WHERE id = :id FOR UPDATE');
            $target->execute(['id' => $targetId]);
            $oldRole = $target->fetchColumn();
            if ($oldRole === false) {
                throw new RuntimeException('The administrator was not found.');
            }
            if ($oldRole === 'superadmin' && $role === 'admin') {
                $superadmins = $pdo->query("SELECT id FROM admins WHERE role = 'superadmin' FOR UPDATE")->fetchAll();
                $count = count($superadmins);
                if ($count <= 1) {
                    throw new RuntimeException('The last superadmin cannot be demoted.');
                }
            }
            $statement = $pdo->prepare('UPDATE admins SET role = :role WHERE id = :id');
            $statement->execute(['role' => $role, 'id' => $targetId]);
            $pdo->commit();
            if ($targetId === (int) $_SESSION['admin_id']) {
                $_SESSION['role'] = $role;
            }
            $_SESSION['flash'] = 'The administrator role was changed.';
        } elseif ($action === 'delete' && $targetId) {
            if ($targetId === (int) $_SESSION['admin_id']) {
                throw new RuntimeException('You cannot delete your own signed-in account.');
            }
            $pdo = db();
            $pdo->beginTransaction();
            $target = $pdo->prepare('SELECT role FROM admins WHERE id = :id FOR UPDATE');
            $target->execute(['id' => $targetId]);
            $role = $target->fetchColumn();
            if ($role === false) {
                throw new RuntimeException('The administrator was not found.');
            }
            if ($role === 'superadmin') {
                $superadmins = $pdo->query("SELECT id FROM admins WHERE role = 'superadmin' FOR UPDATE")->fetchAll();
                $count = count($superadmins);
                if ($count <= 1) {
                    throw new RuntimeException('The last superadmin cannot be deleted.');
                }
            }
            $statement = $pdo->prepare('DELETE FROM admins WHERE id = :id');
            $statement->execute(['id' => $targetId]);
            $pdo->commit();
            $_SESSION['flash'] = 'The administrator was deleted.';
        } else {
            throw new RuntimeException('Invalid administrator action.');
        }
        header('Location: manage-admins.php');
        exit;
    }
    $admins = db()->query('SELECT id, username, role, created_at FROM admins ORDER BY created_at, id')->fetchAll();
} catch (PDOException $exception) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log($exception->getMessage());
    $error = $exception->getCode() === '23000' ? 'That username is already in use.' : 'The administrator request could not be completed.';
    $admins = db()->query('SELECT id, username, role, created_at FROM admins ORDER BY created_at, id')->fetchAll();
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'The administrator request could not be completed.';
    if (!$exception instanceof RuntimeException) error_log($exception->getMessage());
    $admins = db()->query('SELECT id, username, role, created_at FROM admins ORDER BY created_at, id')->fetchAll();
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Manage administrators — Laleh Barzegar</title><link rel="stylesheet" href="../styles/style.css"></head><body class="admin-page"><main class="admin-shell"><header class="admin-header"><div><p class="admin-eyebrow">Laleh Barzegar</p><h1>Manage administrators</h1></div><?php admin_navigation(); ?></header>
<?php if ($flash): ?><p class="admin-message" role="status"><?= h($flash) ?></p><?php endif; ?><?php if ($error): ?><p class="admin-message admin-error" role="alert"><?= h($error) ?></p><?php endif; ?>
<div class="admin-grid"><section><h2>Create New Admin</h2><form class="admin-form" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><label>Username<input name="username" maxlength="100" required></label><label>Password <span>(at least 12 characters)</span><input type="password" name="password" minlength="12" autocomplete="new-password" required></label><label>Confirm Password<input type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required></label><label>Role<select name="role"><option value="admin">admin</option><option value="superadmin">superadmin</option></select></label><button name="action" value="create">Create Admin</button></form></section>
<section><h2>Existing administrators</h2><?php foreach ($admins as $admin): ?><article class="admin-account"><h3><?= h($admin['username']) ?></h3><p class="admin-post-meta"><?= h($admin['role']) ?> · created <?= h($admin['created_at']) ?></p>
<details><summary>Change Password</summary><form class="admin-form compact" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="admin_id" value="<?= (int) $admin['id'] ?>"><label>New Password<input type="password" name="new_password" minlength="12" required></label><label>Confirm Password<input type="password" name="new_password_confirmation" minlength="12" required></label><button name="action" value="password">Change Password</button></form></details>
<form class="admin-inline-form" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="admin_id" value="<?= (int) $admin['id'] ?>"><label>Role <select name="role"><option value="admin"<?= $admin['role'] === 'admin' ? ' selected' : '' ?>>admin</option><option value="superadmin"<?= $admin['role'] === 'superadmin' ? ' selected' : '' ?>>superadmin</option></select></label><button name="action" value="role">Change Role</button></form>
<form class="admin-inline-form" method="post" onsubmit="return confirm('Delete this administrator permanently?');"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="admin_id" value="<?= (int) $admin['id'] ?>"><button name="action" value="delete"<?= (int) $admin['id'] === (int) $_SESSION['admin_id'] ? ' disabled title="You cannot delete your signed-in account"' : '' ?>>Delete Admin</button></form></article><?php endforeach; ?></section></div></main></body></html>
