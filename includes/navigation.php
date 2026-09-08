<?php

declare(strict_types=1);

function render_navigation(string $current = ''): void
{
    $items = [
        'home' => ['HOME', 'index.html'],
        'films' => ['FILMS', 'films.html'],
        'paintings' => ['PAINTINGS', 'paintings.html'],
        'writing' => ['WRITING', 'writing.html'],
        'about' => ['ABOUT', 'about.html'],
        'cv' => ['CV', 'cv.html'],
        'news' => ['NEWS', 'news.php'],
        'contact' => ['CONTACT', 'contact.html'],
    ];
    ?>
    <button class="menu-toggle" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="site-menu">
        <span></span><span></span><span></span><span></span>
    </button>
    <div class="menu-overlay" id="site-menu" aria-hidden="true">
        <nav aria-label="Main navigation">
            <ul>
                <?php foreach ($items as $key => [$label, $href]): ?>
                    <li><a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"<?= $current === $key ? ' aria-current="page"' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
    </div>
    <?php
}
