<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if ($argc !== 2 || trim($argv[1]) === '' || strlen(trim($argv[1])) > 100) {
    fwrite(STDERR, "Usage: php admin/create_admin.php <username>\n");
    exit(1);
}
$username = trim($argv[1]);
fwrite(STDOUT, 'Admin password (at least 12 characters): ');
shell_exec('stty -echo');
$password = rtrim((string) fgets(STDIN), "\r\n");
shell_exec('stty echo');
fwrite(STDOUT, "\n");
if (strlen($password) < 12) {
    fwrite(STDERR, "The password must contain at least 12 characters.\n");
    exit(1);
}
$statement = db()->prepare('INSERT INTO admins (username, password_hash) VALUES (:username, :password_hash)');
$statement->execute(['username' => $username, 'password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
fwrite(STDOUT, "Administrator saved.\n");
