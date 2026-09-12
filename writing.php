<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/navigation.php';

$books = [];
$booksUnavailable = false;

try {
    $statement = db()->query(
        'SELECT `id`, `Name`, `Year`, `Language`, `Description`, `URL`
         FROM `Books`
         ORDER BY `id` ASC'
    );
    $books = $statement->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $booksUnavailable = true;
}

function writing_h(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Books by Laleh Barzegar.">
<title>Writing — Laleh Barzegar</title>
<link rel="stylesheet" href="styles/style.css">
</head>
<body id="top">
<?php render_navigation('writing'); ?>
<main>
<header class="page-title-header">
<h1 class="page-title">WRITING</h1>
</header>
<div class="writing-page">
<?php if ($booksUnavailable): ?>
<p class="writing-message">Books are temporarily unavailable.</p>
<?php elseif ($books === []): ?>
<p class="writing-message">No books are available.</p>
<?php endif; ?>

<?php foreach ($books as $book): ?>
<?php
$year = trim((string) ($book['Year'] ?? ''));
$language = trim((string) ($book['Language'] ?? ''));
$description = trim((string) ($book['Description'] ?? ''));
$imageUrl = (string) ($book['URL'] ?? '');
?>
<article class="book-entry reveal" data-book-id="<?= (int) $book['id'] ?>">
<header class="book-entry-header">
<h2 class="book-entry-title"><?= writing_h($book['Name']) ?></h2>
<?php if ($year !== '' || $language !== ''): ?>
<p class="book-entry-meta"><?php if ($year !== ''): ?><span class="book-entry-year"><?= writing_h($year) ?></span><?php endif; ?><?php if ($year !== '' && $language !== ''): ?>, <?php endif; ?><?php if ($language !== ''): ?><span class="book-entry-language"><?= writing_h($language) ?></span><?php endif; ?></p>
<?php endif; ?>
</header>
<div class="book-entry-content">
<div class="book-entry-image">
<?php if ($imageUrl !== ''): ?>
<img src="<?= writing_h($imageUrl) ?>" alt="">
<?php endif; ?>
</div>
<div class="book-entry-description"><?php if ($description !== ''): ?><p><?= writing_h($description) ?></p><?php endif; ?></div>
</div>
</article>
<?php endforeach; ?>
</div>
</main>
<footer class="site-footer"><span>© <span data-year>2026</span> Laleh Barzegar</span><a href="#top">Back to top</a></footer>
<script src="src/script.js"></script>
</body>
</html>
