<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

$artist = [
    'About' => '',
    'Photo_URL' => '',
];

try {
    $artistStatement = db()->query(
        'SELECT `About`, `Photo_URL` FROM `Artist` LIMIT 1'
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
<body id="top">
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
<li><a href="films.html">FILMS</a></li>
<li><a href="paintings.html">PAINTINGS</a></li>
<li><a href="writing.html">WRITING</a></li>
<li><a href="about.html" aria-current="page">ABOUT</a></li>
<li><a href="news.php">NEWS</a></li>
<li><a href="contact.html">CONTACT</a></li>
</ul>
</nav>
</div>
<main>
<header class="page-title-header">
<h1 class="page-title">ABOUT</h1>
</header>
<section class="about-layout">
<figure class="reveal">
<img src="<?= about_h($artist['Photo_URL']) ?>" alt="Portrait of artist Laleh Barzegar in her studio">
</figure>
<article class="about-copy reveal">
<h2>Laleh Barzegar</h2>
<p><?= nl2br(about_h($artist['About'])) ?></p>
</article>
</section>
</main>
<footer class="site-footer">
<span>© <span data-year>2026</span> Laleh Barzegar</span>
<a href="#top">Back to top</a>
</footer>
<script src="src/script.js">
</script>
</body>
</html>
