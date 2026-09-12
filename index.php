<?php

declare(strict_types=1);
require_once __DIR__ . '/config.php';

$homePictures = [];
$artistFirstPage = '';
try {
    $statement = db()->prepare(
        'SELECT `URL`, `Description`
         FROM `HomePics`
         ORDER BY `id` ASC'
    );
    $statement->execute();
    $homePictures = $statement->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
}

try {
    $artistStatement = db()->query(
        'SELECT `FirstPage`
         FROM `Artist`
         LIMIT 1'
    );
    $artistRecord = $artistStatement->fetch(PDO::FETCH_ASSOC);
    if (is_array($artistRecord)) {
        $artistFirstPage = (string) ($artistRecord['FirstPage'] ?? '');
    }
} catch (Throwable $exception) {
    error_log($exception->getMessage());
}

function home_h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Selected paintings, moving image, and interdisciplinary work by Laleh Barzegar.">
<title>Laleh Barzegar — Artist</title>
<link rel="stylesheet" href="styles/style.css">
</head>
<body class="home-page">
<header class="site-header">
<button class="menu-toggle" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="site-menu">
<span></span>
<span></span>
<span></span>
<span></span>
</button>
</header>
<div class="menu-overlay" id="site-menu" aria-hidden="true">
<nav aria-label="Main navigation">
<ul>
<li><a href="index.html" aria-current="page">HOME</a></li>
<li><a href="about.html">ABOUT</a></li>
<li><a href="films.html">FILMS</a></li>
<li><a href="paintings.html">PAINTINGS</a></li>
<li><a href="writing.html">WRITING</a></li>
<li><a href="news.php">NEWS</a></li>
<li><a href="contact.html">CONTACT</a></li>
</ul>
</nav>
</div>
<main class="home-content">
<section class="home-hero" aria-labelledby="home-title">
<div class="home-hero-heading">
<h1 id="home-title">LALEH BARZEGAR</h1>
<p class="home-hero-professions" aria-label="Artist, filmmaker, writer"><span>| ARTIST</span> <span>| FILMMAKER</span> <span>| WRITER</span> <span>|</span></p>
</div>
<a class="home-scroll-cue" href="#home-gallery" aria-label="Scroll to selected works">
<svg viewBox="0 0 72 24" aria-hidden="true" focusable="false">
<path d="M2 2l34 20L70 2"></path>
</svg>
</a>
</section>

<section class="home-sequence" id="home-gallery" aria-label="Selected works">
<figure class="home-artwork home-artwork--artist">
<div class="home-artwork-image">
<img src="assets/images/home/p1/h02.JPG" alt="Artwork by Laleh Barzegar" loading="lazy">
</div>
<figcaption class="home-artist-text">
<p><?= nl2br(home_h($artistFirstPage)) ?></p>
</figcaption>
</figure>
<?php foreach ($homePictures as $homePicture): ?>
<figure class="home-artwork reveal">
<div class="home-artwork-image">
<img src="<?= home_h($homePicture['URL']) ?>" alt="Artwork by Laleh Barzegar" loading="lazy">
</div>
<?php if (trim((string) ($homePicture['Description'] ?? '')) !== ''): ?>
<figcaption class="home-artwork-caption">
<p class="home-artwork-description"><?= home_h($homePicture['Description']) ?></p>
</figcaption>
<?php endif; ?>
</figure>
<?php endforeach; ?>
</section>
</main>
<footer class="home-footer">
<div class="home-socials" aria-label="Social media">
<!-- Replace # below with Laleh Barzegar's Instagram URL. -->
<a href="#" aria-label="Instagram">
<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"></rect><circle cx="12" cy="12" r="4.25"></circle><circle class="social-icon-fill" cx="17.4" cy="6.7" r="1.1"></circle></svg>
</a>
<!-- Replace # below with Laleh Barzegar's X URL. -->
<a href="#" aria-label="X">
<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 3l12.9 18H20L7.1 3H4zm.2 18L10.8 13M13.2 10.8L19.8 3"></path></svg>
</a>
</div>
<p>Powered by <a href="https://www.nema.one">www.nema.one</a></p>
</footer>
<script src="src/script.js">
</script>
</body>
</html>
