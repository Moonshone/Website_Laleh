<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_name('laleh_admin');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Strict',
        'path' => '/',
    ]);
    session_start();
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $token)) {
        http_response_code(403);
        exit('Invalid request token. Please return to the previous page and try again.');
    }
}

function valid_admin_email(string $email): bool
{
    return strlen($email) <= 255 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function send_password_reset_email(string $recipient, string $token): bool
{
    $appUrl = rtrim(app_setting('APP_URL'), '/');
    $from = app_setting('MAIL_FROM');
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL)
        || !preg_match('#^https://[^/]+(?:/.*)?$#i', $appUrl)) {
        error_log('Password reset email configuration is incomplete.');
        return false;
    }
    $resetUrl = $appUrl . '/admin/reset-password.php?token=' . rawurlencode($token);
    $subject = 'Administrator password reset';
    $message = "A password reset was requested for your administrator account.\n\n"
        . "Reset your password using this secure link:\n{$resetUrl}\n\n"
        . "This one-time link expires in 30 minutes. If you did not request this reset, you can ignore this email.\n";
    $headers = [
        'From: ' . $from,
        'Reply-To: ' . $from,
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . PHP_VERSION,
    ];
    return mail($recipient, $subject, $message, implode("\r\n", $headers));
}

function authenticated_admin(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }

    $statement = db()->prepare('SELECT username, role FROM admins WHERE id = :id LIMIT 1');
    $statement->execute(['id' => (int) $_SESSION['admin_id']]);
    $admin = $statement->fetch();

    if (!$admin || !in_array($admin['role'], ['superadmin', 'admin'], true)) {
        return null;
    }

    return $admin;
}

function require_admin(): void
{
    if (empty($_SESSION['admin_id'])) {
        header('Location: /admin/login.php');
        exit;
    }

    try {
        $admin = authenticated_admin();
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        http_response_code(503);
        exit('Administration is temporarily unavailable.');
    }
    if (!$admin) {
        $_SESSION = [];
        session_regenerate_id(true);
        header('Location: /admin/login.php');
        exit;
    }
    // Refresh authorization on every protected request so role changes and
    // deleted accounts take effect without waiting for the session to expire.
    $_SESSION['username'] = (string) $admin['username'];
    $_SESSION['role'] = (string) $admin['role'];
}

function require_superadmin(): void
{
    require_admin();
    if (($_SESSION['role'] ?? '') !== 'superadmin') {
        http_response_code(403);
        exit('Access denied.');
    }
}

function admin_navigation(): void
{
    ?>
    <nav class="admin-navigation" aria-label="Administration">
        <a href="/admin/dashboard.php">Dashboard</a>
        <a href="/admin/news.php">NEWS Posts</a>
        <a href="/news.php?from=admin">View News Page</a>
        <?php if (($_SESSION['role'] ?? '') === 'superadmin'): ?>
            <a href="/admin/manage-admins.php">Manage Admins</a>
        <?php endif; ?>
        <a href="/admin/account.php">Account</a>
        <form action="logout.php" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><button class="text-button" type="submit">LOG OUT</button></form>
    </nav>
    <?php
}

function delete_news_image(?string $path): void
{
    if (!$path || !str_starts_with($path, 'uploads/news/')) {
        return;
    }
    $fullPath = dirname(__DIR__) . '/' . $path;
    $uploadRoot = realpath(dirname(__DIR__) . '/uploads/news');
    $realPath = realpath($fullPath);
    if ($uploadRoot && $realPath && str_starts_with($realPath, $uploadRoot . DIRECTORY_SEPARATOR)) {
        unlink($realPath);
    }
}

function store_news_image(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('The image upload failed.');
    }
    if (($file['size'] ?? 0) > 8 * 1024 * 1024) {
        throw new RuntimeException('Images may not be larger than 8 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) {
        throw new RuntimeException('Please upload a JPG, PNG, or WEBP image.');
    }
    $directory = dirname(__DIR__) . '/uploads/news';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('The upload directory could not be created.');
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) {
        throw new RuntimeException('The image could not be saved.');
    }
    return 'uploads/news/' . $filename;
}
