<?php

declare(strict_types=1);
require_once __DIR__ . '/admin/bootstrap.php';
require_once __DIR__ . '/includes/navigation.php';

$showAdminBackLink = false;
if (($_GET['from'] ?? '') === 'admin') {
    try {
        $showAdminBackLink = authenticated_admin() !== null;
    } catch (Throwable $exception) {
        // Authentication failure must not prevent public access to the NEWS page.
        error_log($exception->getMessage());
    }
}

$posts = [];
$unavailable = false;
try {
    $statement = db()->prepare("SELECT id, title, content, image, published_at, title_font_size, title_bold, title_italic, title_underline, title_alignment, text_font_size, text_bold, text_italic, text_underline, text_alignment FROM news_posts WHERE status = :status AND published_at IS NOT NULL AND published_at <= NOW() ORDER BY published_at DESC, id DESC");
    $statement->execute(['status' => 'published']);
    $posts = $statement->fetchAll();
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $unavailable = true;
}

function news_h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function news_format_style(array $post, string $prefix, bool $allowJustify): array
{
    $sizes = [12, 14, 16, 18, 20, 22, 24, 28, 32, 36, 40, 48];
    $size = filter_var($post[$prefix . '_font_size'] ?? null, FILTER_VALIDATE_INT);
    $alignment = (string) ($post[$prefix . '_alignment'] ?? 'left');
    $alignments = $allowJustify ? ['left', 'center', 'right', 'justify'] : ['left', 'center', 'right'];
    $styles = [
        'font-weight:' . ((string) ($post[$prefix . '_bold'] ?? '0') === '1' ? '700' : '400'),
        'font-style:' . ((string) ($post[$prefix . '_italic'] ?? '0') === '1' ? 'italic' : 'normal'),
        'text-decoration:' . ((string) ($post[$prefix . '_underline'] ?? '0') === '1' ? 'underline' : 'none'),
        'text-align:' . (in_array($alignment, $alignments, true) ? $alignment : 'left'),
    ];
    $hasCustomSize = in_array($size, $sizes, true);
    if ($hasCustomSize) {
        $styles[] = '--news-' . $prefix . '-font-size:' . $size . 'px';
    }

    return ['style' => implode(';', $styles), 'custom_size' => $hasCustomSize];
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="description" content="News from Laleh Barzegar."><title>News — Laleh Barzegar</title><link rel="stylesheet" href="styles/style.css"></head>
<body id="top"><?php render_navigation('news'); ?>
<main><header class="page-title-header"><h1 class="page-title">NEWS</h1><?php if ($showAdminBackLink): ?><a class="news-admin-back" href="/admin/">&larr; Back to Admin</a><?php endif; ?></header><section class="news-list" aria-label="News posts">
<?php if ($unavailable): ?><p class="news-empty">News is temporarily unavailable.</p><?php elseif (!$posts): ?><p class="news-empty">No news has been published yet.</p><?php endif; ?>
<?php foreach ($posts as $post): $titleFormat = news_format_style($post, 'title', false); $textFormat = news_format_style($post, 'text', true); ?><article class="news-post reveal"><time datetime="<?= news_h(date('c', strtotime($post['published_at']))) ?>"><?= news_h(date('F j, Y', strtotime($post['published_at']))) ?></time><h2<?= $titleFormat['custom_size'] ? ' class="news-custom-title-size"' : '' ?> style="<?= news_h($titleFormat['style']) ?>"><?= news_h($post['title']) ?></h2><?php if ($post['image']): ?><img src="<?= news_h($post['image']) ?>" alt=""><?php endif; ?><div class="news-content<?= $textFormat['custom_size'] ? ' news-custom-text-size' : '' ?>" style="<?= news_h($textFormat['style']) ?>"><?= nl2br(news_h($post['content'])) ?></div></article><?php endforeach; ?>
</section></main><footer class="site-footer"><span>© <span data-year>2026</span> Laleh Barzegar</span><a href="#top">Back to top</a></footer><script src="src/script.js"></script></body></html>
