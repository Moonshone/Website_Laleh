<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

$artist = [
    'About' => '',
    'Photo_URL' => '',
    'FILMOGRAPHY' => '',
    'SOLO EXHIBITIONS' => '',
    'GROUP EXHIBITIONS' => '',
    'WRITING' => '',
    'JURY & PROFESSIONAL ACTIVITIES' => '',
    'TEACHING' => '',
    'ARTIST RESIDENCY' => '',
    'WORKSHOPS & MASTERCLASSES' => '',
    'MEMBERSHIP' => '',
];

try {
    $artistStatement = db()->query(
        'SELECT
            `About`,
            `Photo_URL`,
            `FILMOGRAPHY`,
            `SOLO EXHIBITIONS`,
            `GROUP EXHIBITIONS`,
            `WRITING`,
            `JURY & PROFESSIONAL ACTIVITIES`,
            `TEACHING`,
            `ARTIST RESIDENCY`,
            `WORKSHOPS & MASTERCLASSES`,
            `MEMBERSHIP`
        FROM `Artist`
        LIMIT 1'
    );
    $artistRecord = $artistStatement->fetch(PDO::FETCH_ASSOC);
    if (is_array($artistRecord)) {
        $artist = $artistRecord;
    }
} catch (Throwable $exception) {
    error_log($exception->getMessage());
}

function about_h(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$selectedCvFields = [
    'FILMOGRAPHY',
    'SOLO EXHIBITIONS',
    'GROUP EXHIBITIONS',
    'WRITING',
    'JURY & PROFESSIONAL ACTIVITIES',
    'TEACHING',
    'ARTIST RESIDENCY',
    'WORKSHOPS & MASTERCLASSES',
    'MEMBERSHIP',
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Biography and artist statement for Laleh Barzegar.">
<title>About — Laleh Barzegar</title>
<link rel="stylesheet" href="styles/style.css">
</head>
<body id="top" class="about-view">
<button class="menu-toggle" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="site-menu">
<span>
</span>
<span>
</span>
<span>
</span>
<span>
</span>
</button>
<div class="menu-overlay" id="site-menu" aria-hidden="true">
<nav aria-label="Main navigation">
<ul>
<li><a href="index.html">HOME</a></li>
<li><a href="about.html" aria-current="page">ABOUT</a></li>
<li><a href="films.html">FILMS</a></li>
<li><a href="paintings.html">PAINTINGS</a></li>
<li><a href="writing.html">WRITING</a></li>
<li><a href="news.php">NEWS</a></li>
<li><a href="contact.html">CONTACT</a></li>
</ul>
</nav>
</div>
<main>
<header class="page-title-header">
<h1 class="page-title">ABOUT</h1>
</header>
<div class="about-page">
<section class="about-intro reveal" aria-labelledby="artist-name">
<figure class="about-portrait">
<img src="<?= about_h($artist['Photo_URL']) ?>" alt="Portrait of artist Laleh Barzegar in her studio">
</figure>
<article class="about-copy">
<h2 id="artist-name">Laleh Barzegar</h2>
<p><?= nl2br(about_h($artist['About'])) ?></p>
</article>
</section>
<section class="selected-cv reveal" aria-labelledby="selected-cv-title">
<h2 id="selected-cv-title">SELECTED CV</h2>
<div class="selected-cv-grid">
<?php foreach ($selectedCvFields as $field): ?>
<?php if (!empty($artist[$field])): ?>
<div class="selected-cv-section">
<h3 class="selected-cv-category"><?= about_h($field) ?></h3>
<p><?= nl2br(about_h($artist[$field])) ?></p>
</div>
<?php endif; ?>
<?php endforeach; ?>
</div>
</section>
</div>
</main>
<footer class="site-footer">
<span>© <span data-year>2026</span> Laleh Barzegar</span>
<a href="#top">Back to top</a>
</footer>
<script src="src/script.js">
</script>
</body>
</html>
