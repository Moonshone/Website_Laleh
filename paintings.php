<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/navigation.php';

$paintingActivities = [];
$paintingsUnavailable = false;

try {
    $connection = db();
    $activityStatement = $connection->query(
        'SELECT `id`, `Name`, `Activity`, `Year`, `Location`, `Description`
         FROM `PaintingActivities`
         ORDER BY `id` ASC'
    );
    $paintingActivities = $activityStatement->fetchAll(PDO::FETCH_ASSOC);

    $schemaStatement = $connection->query(
        'SELECT `TABLE_NAME`, `COLUMN_NAME`
         FROM `INFORMATION_SCHEMA`.`COLUMNS`
         WHERE `TABLE_SCHEMA` = DATABASE()'
    );
    $tableColumns = [];
    foreach ($schemaStatement->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $tableColumns[(string) $column['TABLE_NAME']][(string) $column['COLUMN_NAME']] = true;
    }

    foreach ($paintingActivities as &$paintingActivity) {
        $paintingActivity['images'] = [];
        $tableName = (string) ($paintingActivity['Name'] ?? '');

        // A dynamic identifier cannot be parameter-bound. Only use an exact table name
        // discovered in the current schema and only when its required columns exist.
        if (
            $tableName === ''
            || !isset($tableColumns[$tableName])
            || !isset($tableColumns[$tableName]['ActivitiesID'], $tableColumns[$tableName]['URL'])
        ) {
            continue;
        }

        try {
            $quotedTableName = '`' . str_replace('`', '``', $tableName) . '`';
            $imageStatement = $connection->prepare(
                "SELECT `URL` FROM {$quotedTableName} WHERE `ActivitiesID` = :activity_id"
            );
            $imageStatement->execute(['activity_id' => $paintingActivity['id']]);

            foreach ($imageStatement->fetchAll(PDO::FETCH_COLUMN) as $url) {
                $url = (string) $url;
                $path = parse_url($url, PHP_URL_PATH);
                if ($url !== '' && is_string($path) && preg_match('/\.(?:jpe?g|png)$/i', $path) === 1) {
                    // Keep the database value untouched; only the extension check is case-insensitive.
                    $paintingActivity['images'][] = $url;
                }
            }
        } catch (Throwable $exception) {
            // One missing or malformed activity table must not break the other galleries.
            error_log($exception->getMessage());
        }
    }
    unset($paintingActivity);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $paintingsUnavailable = true;
}

function paintings_h(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function paintings_details(array $activity): string
{
    $details = [];
    foreach (['Activity', 'Year', 'Location'] as $field) {
        $value = trim((string) ($activity[$field] ?? ''));
        if ($value !== '') {
            $details[] = $value;
        }
    }

    return implode(', ', $details);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Painting activities by Laleh Barzegar.">
<title>Paintings — Laleh Barzegar</title>
<link rel="stylesheet" href="styles/style.css">
<style>
.paintings-page { padding: clamp(8rem, 14vw, 12rem) var(--gutter) clamp(6rem, 12vw, 11rem); }
.painting-activity { width: min(100%, 82rem); margin: 0 auto clamp(7rem, 13vw, 13rem); }
.painting-activity:last-child { margin-bottom: 0; }
.painting-activity-header { margin-bottom: clamp(1.5rem, 3vw, 2.75rem); }
.painting-activity-title { margin: 0; font-size: clamp(2rem, 5vw, 5rem); line-height: 1; letter-spacing: -.035em; }
.painting-activity-meta { margin: .65rem 0 0; color: var(--muted); font-size: .75rem; letter-spacing: .05em; }
.painting-slideshow { position: relative; background: var(--artwork-frame); border: 1px solid var(--artwork-border); }
.painting-slide { display: none; min-height: min(70vw, 48rem); padding: clamp(.75rem, 2.5vw, 2.5rem); place-items: center; }
.painting-slide.is-active { display: grid; }
.painting-slide img { width: auto; max-width: 100%; height: auto; max-height: min(78vh, 48rem); object-fit: contain; }
.painting-slideshow-button { position: absolute; z-index: 2; top: 50%; width: 3rem; height: 3rem; padding: 0; border: 1px solid rgba(20,20,20,.25); border-radius: 50%; transform: translateY(-50%); background: rgba(244,241,234,.88); color: var(--ink); cursor: pointer; font-size: 1.35rem; line-height: 1; }
.painting-slideshow-button:hover, .painting-slideshow-button:focus-visible { background: var(--paper); }
.painting-slideshow-button--previous { left: clamp(.5rem, 1.5vw, 1.25rem); }
.painting-slideshow-button--next { right: clamp(.5rem, 1.5vw, 1.25rem); }
.painting-slideshow-status { position: absolute; right: 1rem; bottom: .65rem; margin: 0; padding: .15rem .4rem; background: rgba(244,241,234,.88); font-size: .65rem; }
.painting-activity-description { max-width: 48rem; margin: clamp(1.5rem, 3vw, 2.75rem) 0 0; color: var(--muted); line-height: 1.75; white-space: pre-line; }
.painting-empty, .paintings-message { margin: 0; padding: clamp(3rem, 8vw, 7rem) 1rem; color: var(--muted); text-align: center; }
@media (max-width: 720px) {
  .paintings-page { padding-top: 7rem; }
  .painting-activity { margin-bottom: 7rem; }
  .painting-slide { min-height: 70vw; padding: .65rem; }
  .painting-slideshow-button { width: 2.5rem; height: 2.5rem; }
}
</style>
</head>
<body id="top">
<?php render_navigation('paintings'); ?>
<main class="paintings-page">
<?php if ($paintingsUnavailable): ?>
<p class="paintings-message">Paintings are temporarily unavailable.</p>
<?php elseif ($paintingActivities === []): ?>
<p class="paintings-message">No painting activities are available.</p>
<?php endif; ?>

<?php foreach ($paintingActivities as $activityIndex => $paintingActivity): ?>
<?php
$images = $paintingActivity['images'];
$imageCount = count($images);
$slideshowId = 'painting-slideshow-' . (int) $paintingActivity['id'] . '-' . $activityIndex;
?>
<article class="painting-activity reveal">
<header class="painting-activity-header">
<h2 class="painting-activity-title"><?= paintings_h($paintingActivity['Name']) ?></h2>
<?php if (paintings_details($paintingActivity) !== ''): ?>
<p class="painting-activity-meta"><?= paintings_h(paintings_details($paintingActivity)) ?></p>
<?php endif; ?>
</header>

<?php if ($imageCount > 0): ?>
<div class="painting-slideshow" id="<?= paintings_h($slideshowId) ?>" data-painting-slideshow aria-label="<?= paintings_h($paintingActivity['Name']) ?> slideshow">
<?php foreach ($images as $imageIndex => $imageUrl): ?>
<div class="painting-slide<?= $imageIndex === 0 ? ' is-active' : '' ?>" data-painting-slide<?= $imageIndex === 0 ? '' : ' hidden' ?>>
<img src="<?= paintings_h($imageUrl) ?>" alt="<?= paintings_h($paintingActivity['Name']) ?>"<?= $imageIndex === 0 ? '' : ' loading="lazy"' ?>>
</div>
<?php endforeach; ?>
<?php if ($imageCount > 1): ?>
<button class="painting-slideshow-button painting-slideshow-button--previous" type="button" data-painting-previous aria-label="Previous image">&#8592;</button>
<button class="painting-slideshow-button painting-slideshow-button--next" type="button" data-painting-next aria-label="Next image">&#8594;</button>
<p class="painting-slideshow-status" aria-live="polite"><span data-painting-current>1</span> / <?= $imageCount ?></p>
<?php endif; ?>
</div>
<?php else: ?>
<div class="painting-slideshow painting-empty">No images are available for this activity.</div>
<?php endif; ?>

<?php if (trim((string) ($paintingActivity['Description'] ?? '')) !== ''): ?>
<p class="painting-activity-description"><?= paintings_h($paintingActivity['Description']) ?></p>
<?php endif; ?>
</article>
<?php endforeach; ?>
</main>
<footer class="site-footer"><span>© <span data-year>2026</span> Laleh Barzegar</span><a href="#top">Back to top</a></footer>
<script src="src/script.js"></script>
<script>
document.querySelectorAll('[data-painting-slideshow]').forEach((slideshow) => {
  const slides = Array.from(slideshow.querySelectorAll('[data-painting-slide]'));
  const current = slideshow.querySelector('[data-painting-current]');
  let activeIndex = 0;

  const showSlide = (nextIndex) => {
    activeIndex = (nextIndex + slides.length) % slides.length;
    slides.forEach((slide, index) => {
      const isActive = index === activeIndex;
      slide.classList.toggle('is-active', isActive);
      slide.hidden = !isActive;
    });
    if (current) current.textContent = String(activeIndex + 1);
  };

  slideshow.querySelector('[data-painting-previous]')?.addEventListener('click', () => showSlide(activeIndex - 1));
  slideshow.querySelector('[data-painting-next]')?.addEventListener('click', () => showSlide(activeIndex + 1));
});
</script>
</body>
</html>
