<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');

try {
    $statement = db()->prepare(
        "SELECT id, title, content, image, published_at
         FROM news_posts
         WHERE status = :status AND published_at IS NOT NULL AND published_at <= NOW()
         ORDER BY published_at DESC, id DESC"
    );
    $statement->execute(['status' => 'published']);
    echo json_encode(['posts' => $statement->fetchAll()], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    http_response_code(503);
    echo json_encode(['error' => 'News is temporarily unavailable.']);
}
