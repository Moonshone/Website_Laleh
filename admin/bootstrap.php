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

function require_admin(): void
{
    if (empty($_SESSION['admin_id'])) {
        header('Location: /admin/login.php');
        exit;
    }
    try {
        $statement = db()->prepare('SELECT username, role FROM admins WHERE id = :id LIMIT 1');
        $statement->execute(['id' => (int) $_SESSION['admin_id']]);
        $admin = $statement->fetch();
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        http_response_code(503);
        exit('Administration is temporarily unavailable.');
    }
    if (!$admin || !in_array($admin['role'], ['superadmin', 'admin'], true)) {
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
        <a href="dashboard.php">DASHBOARD</a>
        <a href="news.php">NEWS POSTS</a>
        <?php if (($_SESSION['role'] ?? '') === 'superadmin'): ?><a href="manage-admins.php">MANAGE ADMINS</a><?php endif; ?>
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
