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
            || !isset($tableColumns[$tableName]['ActivityID'], $tableColumns[$tableName]['URL'])
        ) {
            continue;
        }

        try {
            $quotedTableName = '`' . str_replace('`', '``', $tableName) . '`';
            $imageStatement = $connection->prepare(
                "SELECT `URL` FROM {$quotedTableName} WHERE `ActivityID` = :activity_id"
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
<article class="painting-activity reveal" data-painting-activity-id="<?= (int) $paintingActivity['id'] ?>">
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
<img src="<?= paintings_h($imageUrl) ?>" alt="<?= paintings_h($paintingActivity['Name']) ?>, image <?= $imageIndex + 1 ?> of <?= $imageCount ?>"<?= $imageIndex === 0 ? '' : ' loading="lazy"' ?>>
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
</body>
</html>
