<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if ($argc !== 3 || trim($argv[1]) === '' || strlen($argv[2]) < 12) {
    fwrite(STDERR, "Usage: php admin/create_admin.php <username> <password-at-least-12-characters>\n");
    exit(1);
}
$statement = db()->prepare('INSERT INTO admin_users (username, password_hash) VALUES (:username, :password_hash) ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)');
$statement->execute(['username' => trim($argv[1]), 'password_hash' => password_hash($argv[2], PASSWORD_DEFAULT)]);
fwrite(STDOUT, "Administrator saved.\n");
