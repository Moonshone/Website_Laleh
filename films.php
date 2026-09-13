<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/navigation.php';
require_once __DIR__ . '/includes/media-security.php';

$films = [];
$filmsUnavailable = false;

try {
    $statement = db()->query(
        'SELECT `id`, `Name`, `Year`, `Genre`, `Duration`, `Description`, `URL`, `Mov_URL`
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
<body id="top" class="films-view">
<?php render_navigation('films'); ?>
<main>
<header class="page-title-header">
<h1 class="page-title">FILMS</h1>
</header>
<div class="films-page">
<?php if ($filmsUnavailable): ?>
<p class="films-message">Films are temporarily unavailable.</p>
<?php elseif ($films === []): ?>
<p class="films-message">No films are available.</p>
<?php endif; ?>

<?php foreach ($films as $film): ?>
<?php
$imageUrl = safe_media_url($film['URL'] ?? null);
$movieUrl = safe_media_url($film['Mov_URL'] ?? null);
$movieUrl = $movieUrl ?? '';
$youtubeEmbedUrl = safe_youtube_embed_url($movieUrl);
$isVideoFile = films_is_video_file($movieUrl);
$metadata = array_values(array_filter([
    trim((string) ($film['Year'] ?? '')),
    trim((string) ($film['Genre'] ?? '')),
    trim((string) ($film['Duration'] ?? '')),
], static fn (string $value): bool => $value !== ''));
?>
<article class="film-entry reveal" data-film-id="<?= (int) $film['id'] ?>">
<?php if (films_has_value($film['Name']) || $metadata !== []): ?>
<header class="film-entry-header">
<?php if (films_has_value($film['Name'])): ?>
<h2 class="film-entry-title"><?= films_h($film['Name']) ?></h2>
<?php endif; ?>
<?php if ($metadata !== []): ?>
<p class="film-entry-meta"><?= films_h(implode(', ', $metadata)) ?></p>
<?php endif; ?>
</header>
<?php endif; ?>

<div class="film-entry-content">
<div class="film-entry-media">
<?php if ($imageUrl !== null): ?>
<div class="film-entry-image">
<img src="<?= films_h($imageUrl) ?>" alt="">
</div>
<?php endif; ?>

<?php if ($youtubeEmbedUrl !== null || $isVideoFile): ?>
<div class="film-entry-movie">
<?php if ($youtubeEmbedUrl !== null): ?>
<iframe src="<?= films_h($youtubeEmbedUrl) ?>" title="<?= films_h($film['Name']) ?>" loading="lazy" sandbox="allow-scripts allow-same-origin allow-presentation" allow="autoplay; fullscreen; picture-in-picture" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
<?php elseif ($isVideoFile): ?>
<video controls preload="metadata">
<source src="<?= films_h($movieUrl) ?>">
</video>
<?php endif; ?>
</div>
<?php endif; ?>
</div>

<div class="film-entry-description"><?php if (films_has_value($film['Description'])): ?><p><?= films_h($film['Description']) ?></p><?php endif; ?></div>
</div>
</article>
<?php endforeach; ?>
</div>
</main>
<footer class="site-footer"><span>© <span data-year>2026</span> Laleh Barzegar</span><a href="#top">Back to top</a></footer>
<script src="src/script.js"></script>
</body>
</html>
