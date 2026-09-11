<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/navigation.php';

$films = [];
$filmsUnavailable = false;

try {
    $statement = db()->query(
        'SELECT `id`, `Name`, `Year`, `Description`, `URL`, `Mov_URL`
         FROM `Films`
         ORDER BY `id` ASC'
    );
    $films = $statement->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $filmsUnavailable = true;
}

function films_h(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function films_has_value(mixed $value): bool
{
    return trim((string) ($value ?? '')) !== '';
}

function films_is_video_file(string $url): bool
{
    $path = parse_url($url, PHP_URL_PATH);
    if (!is_string($path)) {
        $path = $url;
    }

    return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['mp4', 'webm', 'mov'], true);
}

function films_youtube_embed_url(string $url): ?string
{
    $parts = parse_url(trim($url));
    if ($parts === false || !isset($parts['host'])) {
        return null;
    }

    $host = strtolower(rtrim($parts['host'], '.'));
    if (str_starts_with($host, 'www.')) {
        $host = substr($host, 4);
    }

    $videoId = null;
    $path = trim((string) ($parts['path'] ?? ''), '/');

    if ($host === 'youtu.be') {
        $videoId = explode('/', $path, 2)[0];
    } elseif (in_array($host, ['youtube.com', 'm.youtube.com'], true)) {
        if ($path === 'watch') {
            parse_str((string) ($parts['query'] ?? ''), $query);
            $videoId = isset($query['v']) && is_string($query['v']) ? $query['v'] : null;
        } elseif (str_starts_with($path, 'embed/')) {
            $videoId = explode('/', substr($path, 6), 2)[0];
        }
    }

    $videoId = rawurldecode(trim((string) $videoId));
    if ($videoId === '' || preg_match('/^[A-Za-z0-9_-]+$/D', $videoId) !== 1) {
        return null;
    }

    return 'https://www.youtube.com/embed/' . $videoId;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Moving-image works by Laleh Barzegar.">
<title>Films — Laleh Barzegar</title>
<link rel="stylesheet" href="styles/style.css">
</head>
<body id="top">
<?php render_navigation('films'); ?>
<main class="films-page">
<?php if ($filmsUnavailable): ?>
<p class="films-message">Films are temporarily unavailable.</p>
<?php elseif ($films === []): ?>
<p class="films-message">No films are available.</p>
<?php endif; ?>

<?php foreach ($films as $film): ?>
<?php
$movieUrl = trim((string) ($film['Mov_URL'] ?? ''));
$youtubeEmbedUrl = films_youtube_embed_url($movieUrl);
?>
<article class="film-entry reveal" data-film-id="<?= (int) $film['id'] ?>">
<?php if (films_has_value($film['Name']) || films_has_value($film['Year'])): ?>
<header class="film-entry-header">
<?php if (films_has_value($film['Name'])): ?>
<h2 class="film-entry-title"><?= films_h($film['Name']) ?></h2>
<?php endif; ?>
<?php if (films_has_value($film['Year'])): ?>
<p class="film-entry-year"><?= films_h($film['Year']) ?></p>
<?php endif; ?>
</header>
<?php endif; ?>

<?php if (films_has_value($film['URL'])): ?>
<div class="film-entry-image">
<img src="<?= films_h($film['URL']) ?>" alt="">
</div>
<?php endif; ?>

<?php if ($movieUrl !== ''): ?>
<div class="film-entry-movie">
<?php if ($youtubeEmbedUrl !== null): ?>
<iframe src="<?= films_h($youtubeEmbedUrl) ?>" title="<?= films_h($film['Name']) ?>" loading="lazy" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
<?php elseif (films_is_video_file($movieUrl)): ?>
<video controls preload="metadata">
<source src="<?= films_h($movieUrl) ?>">
</video>
<?php else: ?>
<iframe src="<?= films_h($movieUrl) ?>" title="<?= films_h($film['Name']) ?>" loading="lazy" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
<?php endif; ?>
</div>
<?php endif; ?>

<?php if (films_has_value($film['Description'])): ?>
<p class="film-entry-description"><?= films_h($film['Description']) ?></p>
<?php endif; ?>
</article>
<?php endforeach; ?>
</main>
<footer class="site-footer"><span>© <span data-year>2026</span> Laleh Barzegar</span><a href="#top">Back to top</a></footer>
<script src="src/script.js"></script>
</body>
</html>
