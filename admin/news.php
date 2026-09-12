<?php

declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_admin();

$error = '';
const NEWS_FONT_SIZES = [12, 14, 16, 18, 20, 22, 24, 28, 32, 36, 40, 48];

function news_format_from_post(string $prefix, bool $allowJustify): array
{
    $fontSize = filter_var($_POST[$prefix . '_font_size'] ?? null, FILTER_VALIDATE_INT);
    $alignment = (string) ($_POST[$prefix . '_alignment'] ?? 'left');
    $alignments = $allowJustify ? ['left', 'center', 'right', 'justify'] : ['left', 'center', 'right'];

    return [
        'font_size' => in_array($fontSize, NEWS_FONT_SIZES, true) ? $fontSize : null,
        'bold' => ($_POST[$prefix . '_bold'] ?? '0') === '1' ? 1 : 0,
        'italic' => ($_POST[$prefix . '_italic'] ?? '0') === '1' ? 1 : 0,
        'underline' => ($_POST[$prefix . '_underline'] ?? '0') === '1' ? 1 : 0,
        'alignment' => in_array($alignment, $alignments, true) ? $alignment : 'left',
    ];
}

$formatDefaults = [
    'title_font_size' => null, 'title_bold' => 0, 'title_italic' => 0,
    'title_underline' => 0, 'title_alignment' => 'left', 'text_font_size' => null,
    'text_bold' => 0, 'text_italic' => 0, 'text_underline' => 0, 'text_alignment' => 'left',
];
$editing = ['id' => '', 'title' => '', 'content' => '', 'image' => null, 'status' => 'draft', 'published_at' => ''] + $formatDefaults;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = (string) ($_POST['action'] ?? '');
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($action === 'delete' && $id) {
            $statement = db()->prepare('SELECT image FROM news_posts WHERE id = :id');
            $statement->execute(['id' => $id]);
            $post = $statement->fetch();
            if ($post) {
                $statement = db()->prepare('DELETE FROM news_posts WHERE id = :id');
                $statement->execute(['id' => $id]);
                delete_news_image($post['image']);
            }
            $_SESSION['flash'] = 'The news post was deleted.';
            header('Location: news.php');
            exit;
        }

        if (in_array($action, ['toggle'], true) && $id) {
            $status = ($_POST['status'] ?? '') === 'published' ? 'published' : 'draft';
            $statement = db()->prepare("UPDATE news_posts SET status = :status, published_at = CASE WHEN :status_date = 'published' THEN COALESCE(published_at, NOW()) ELSE published_at END WHERE id = :id");
            $statement->execute(['status' => $status, 'status_date' => $status, 'id' => $id]);
            $_SESSION['flash'] = $status === 'published' ? 'The news post was published.' : 'The news post was unpublished.';
            header('Location: news.php');
            exit;
        }

        if (in_array($action, ['save', 'publish'], true)) {
            $title = trim((string) ($_POST['title'] ?? ''));
            $content = trim((string) ($_POST['content'] ?? ''));
            $titleFormat = news_format_from_post('title', false);
            $textFormat = news_format_from_post('text', true);
            $status = $action === 'publish' ? 'published' : 'draft';
            $status = in_array($status, ['draft', 'published'], true) ? $status : 'draft';
            $dateInput = trim((string) ($_POST['published_at'] ?? ''));
            $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $dateInput);
            $dateErrors = DateTimeImmutable::getLastErrors();
            $validDate = $date && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0));

            $titleLength = preg_match_all('/./us', $title);
            if ($title === '' || $titleLength === false || $titleLength > 255 || $content === '') {
                throw new RuntimeException('Please enter a title of up to 255 characters and the post text.');
            }
            if ($status === 'published' && !$validDate) {
                throw new RuntimeException('Please enter a valid publication date and time.');
            }
            $publishedAt = $validDate ? $date->format('Y-m-d H:i:s') : null;
            $oldImage = null;
            if ($id) {
                $statement = db()->prepare('SELECT image FROM news_posts WHERE id = :id');
                $statement->execute(['id' => $id]);
                $oldImage = $statement->fetchColumn() ?: null;
            }
            $image = $oldImage;
            if (isset($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $image = store_news_image($_FILES['image']);
            }

            if ($id) {
                $statement = db()->prepare('UPDATE news_posts SET title = :title, content = :content, image = :image, status = :status, published_at = :published_at, title_font_size = :title_font_size, title_bold = :title_bold, title_italic = :title_italic, title_underline = :title_underline, title_alignment = :title_alignment, text_font_size = :text_font_size, text_bold = :text_bold, text_italic = :text_italic, text_underline = :text_underline, text_alignment = :text_alignment WHERE id = :id');
                $statement->execute(compact('title', 'content', 'image', 'status', 'id') + ['published_at' => $publishedAt] + [
                    'title_font_size' => $titleFormat['font_size'], 'title_bold' => $titleFormat['bold'], 'title_italic' => $titleFormat['italic'], 'title_underline' => $titleFormat['underline'], 'title_alignment' => $titleFormat['alignment'],
                    'text_font_size' => $textFormat['font_size'], 'text_bold' => $textFormat['bold'], 'text_italic' => $textFormat['italic'], 'text_underline' => $textFormat['underline'], 'text_alignment' => $textFormat['alignment'],
                ]);
            } else {
                $statement = db()->prepare('INSERT INTO news_posts (title, content, image, status, published_at, title_font_size, title_bold, title_italic, title_underline, title_alignment, text_font_size, text_bold, text_italic, text_underline, text_alignment) VALUES (:title, :content, :image, :status, :published_at, :title_font_size, :title_bold, :title_italic, :title_underline, :title_alignment, :text_font_size, :text_bold, :text_italic, :text_underline, :text_alignment)');
                $statement->execute(compact('title', 'content', 'image', 'status') + ['published_at' => $publishedAt] + [
                    'title_font_size' => $titleFormat['font_size'], 'title_bold' => $titleFormat['bold'], 'title_italic' => $titleFormat['italic'], 'title_underline' => $titleFormat['underline'], 'title_alignment' => $titleFormat['alignment'],
                    'text_font_size' => $textFormat['font_size'], 'text_bold' => $textFormat['bold'], 'text_italic' => $textFormat['italic'], 'text_underline' => $textFormat['underline'], 'text_alignment' => $textFormat['alignment'],
                ]);
            }
            if ($image !== $oldImage) {
                delete_news_image($oldImage);
            }
            $_SESSION['flash'] = $status === 'published' ? 'The news post was published.' : 'The draft was saved.';
            header('Location: news.php');
            exit;
        }
    }

    $editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($editId) {
        $statement = db()->prepare('SELECT * FROM news_posts WHERE id = :id');
        $statement->execute(['id' => $editId]);
        $editing = ($statement->fetch() ?: $editing) + $formatDefaults;
    }
    $posts = db()->query('SELECT id, title, image, status, published_at, updated_at FROM news_posts ORDER BY created_at DESC')->fetchAll();
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'The database request could not be completed.';
    $posts = $posts ?? [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $editing = [
            'id' => (string) ($_POST['id'] ?? ''), 'title' => (string) ($_POST['title'] ?? ''),
            'content' => (string) ($_POST['content'] ?? ''), 'image' => null,
            'status' => (string) ($_POST['status'] ?? 'draft'), 'published_at' => (string) ($_POST['published_at'] ?? ''),
        ] + [
            'title_font_size' => $_POST['title_font_size'] ?? null, 'title_bold' => (int) (($_POST['title_bold'] ?? '0') === '1'),
            'title_italic' => (int) (($_POST['title_italic'] ?? '0') === '1'), 'title_underline' => (int) (($_POST['title_underline'] ?? '0') === '1'),
            'title_alignment' => (string) ($_POST['title_alignment'] ?? 'left'), 'text_font_size' => $_POST['text_font_size'] ?? null,
            'text_bold' => (int) (($_POST['text_bold'] ?? '0') === '1'), 'text_italic' => (int) (($_POST['text_italic'] ?? '0') === '1'),
            'text_underline' => (int) (($_POST['text_underline'] ?? '0') === '1'), 'text_alignment' => (string) ($_POST['text_alignment'] ?? 'left'),
        ];
    }
}
$publicationValue = !empty($editing['published_at']) ? date('Y-m-d\TH:i', strtotime((string) $editing['published_at'])) : date('Y-m-d\TH:i');
$flash = (string) ($_SESSION['flash'] ?? '');
unset($_SESSION['flash']);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Manage news — Laleh Barzegar</title><link rel="stylesheet" href="../styles/style.css"></head>
<body class="admin-page"><main class="admin-shell"><header class="admin-header"><div><p class="admin-eyebrow">Laleh Barzegar</p><h1>Manage news</h1></div><?php admin_navigation(); ?></header>
<?php if ($flash): ?><p class="admin-message" role="status"><?= h($flash) ?></p><?php endif; ?><?php if ($error): ?><p class="admin-message admin-error" role="alert"><?= h($error) ?></p><?php endif; ?>
<div class="admin-grid"><section><h2><?= $editing['id'] ? 'Edit post' : 'New post' ?></h2><form class="admin-form" method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="id" value="<?= h((string) $editing['id']) ?>">
<div class="admin-format-field"><label for="news-title">Title</label>
<div class="format-toolbar" data-format-toolbar aria-label="Title formatting">
<label class="format-size"><span>Size</span><select name="title_font_size" aria-label="Title font size"><option value="">Default</option><?php foreach (NEWS_FONT_SIZES as $size): ?><option value="<?= $size ?>"<?= (string) $editing['title_font_size'] === (string) $size ? ' selected' : '' ?>><?= $size ?></option><?php endforeach; ?></select></label>
<?php foreach (['bold' => 'B', 'italic' => 'I', 'underline' => 'U'] as $format => $caption): ?><input type="hidden" name="title_<?= $format ?>" value="<?= !empty($editing['title_' . $format]) ? '1' : '0' ?>"><button type="button" class="format-button format-<?= $format ?><?= !empty($editing['title_' . $format]) ? ' is-active' : '' ?>" data-format-toggle="title_<?= $format ?>" aria-pressed="<?= !empty($editing['title_' . $format]) ? 'true' : 'false' ?>" title="<?= ucfirst($format) ?>"><?= $caption ?></button><?php endforeach; ?>
<input type="hidden" name="title_alignment" value="<?= h(in_array($editing['title_alignment'], ['left', 'center', 'right'], true) ? $editing['title_alignment'] : 'left') ?>"><div class="format-alignments" role="group" aria-label="Title alignment"><?php foreach (['left' => 'Align left', 'center' => 'Align center', 'right' => 'Align right'] as $alignment => $label): ?><button type="button" class="format-button format-align format-align-<?= $alignment ?><?= $editing['title_alignment'] === $alignment ? ' is-active' : '' ?>" data-format-align="title_alignment" data-value="<?= $alignment ?>" aria-label="<?= $label ?>" aria-pressed="<?= $editing['title_alignment'] === $alignment ? 'true' : 'false' ?>"><span></span><span></span><span></span></button><?php endforeach; ?></div>
</div><input id="news-title" name="title" maxlength="255" value="<?= h($editing['title']) ?>" required></div>
<div class="admin-format-field"><label for="news-content">Text</label>
<div class="format-toolbar" data-format-toolbar aria-label="Text formatting">
<label class="format-size"><span>Size</span><select name="text_font_size" aria-label="Text font size"><option value="">Default</option><?php foreach (NEWS_FONT_SIZES as $size): ?><option value="<?= $size ?>"<?= (string) $editing['text_font_size'] === (string) $size ? ' selected' : '' ?>><?= $size ?></option><?php endforeach; ?></select></label>
<?php foreach (['bold' => 'B', 'italic' => 'I', 'underline' => 'U'] as $format => $caption): ?><input type="hidden" name="text_<?= $format ?>" value="<?= !empty($editing['text_' . $format]) ? '1' : '0' ?>"><button type="button" class="format-button format-<?= $format ?><?= !empty($editing['text_' . $format]) ? ' is-active' : '' ?>" data-format-toggle="text_<?= $format ?>" aria-pressed="<?= !empty($editing['text_' . $format]) ? 'true' : 'false' ?>" title="<?= ucfirst($format) ?>"><?= $caption ?></button><?php endforeach; ?>
<input type="hidden" name="text_alignment" value="<?= h(in_array($editing['text_alignment'], ['left', 'center', 'right', 'justify'], true) ? $editing['text_alignment'] : 'left') ?>"><div class="format-alignments" role="group" aria-label="Text alignment"><?php foreach (['left' => 'Align left', 'center' => 'Align center', 'right' => 'Align right', 'justify' => 'Justify'] as $alignment => $label): ?><button type="button" class="format-button format-align format-align-<?= $alignment ?><?= $editing['text_alignment'] === $alignment ? ' is-active' : '' ?>" data-format-align="text_alignment" data-value="<?= $alignment ?>" aria-label="<?= $label ?>" aria-pressed="<?= $editing['text_alignment'] === $alignment ? 'true' : 'false' ?>"><span></span><span></span><span></span></button><?php endforeach; ?></div>
</div><textarea id="news-content" name="content" rows="14" required><?= h($editing['content']) ?></textarea></div>
<label>Image <span>(JPG, PNG or WEBP, max. 8 MB)</span><input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
<?php if ($editing['image']): ?><img class="admin-image-preview" src="../<?= h($editing['image']) ?>" alt="Current post image"><?php endif; ?>
<label>Current status <span>(set with the buttons below)</span><select disabled><option value="draft"<?= $editing['status'] === 'draft' ? ' selected' : '' ?>>Draft</option><option value="published"<?= $editing['status'] === 'published' ? ' selected' : '' ?>>Published</option></select></label>
<label>Publication date<input type="datetime-local" name="published_at" value="<?= h($publicationValue) ?>"></label>
<div class="admin-actions"><button type="submit" name="action" value="save">SAVE DRAFT</button><button type="submit" name="action" value="publish">PUBLISH</button><?php if ($editing['id']): ?><a href="news.php">CANCEL</a><?php endif; ?></div>
</form></section><section><h2>Existing posts</h2><div class="admin-posts">
<?php if (!$posts): ?><p>No news posts have been created.</p><?php endif; ?>
<?php foreach ($posts as $post): ?><article class="admin-post"><div><p class="admin-post-meta"><?= h(ucfirst($post['status'])) ?> · <?= h($post['published_at'] ?: 'No publication date') ?></p><h3><?= h($post['title']) ?></h3></div><div class="admin-post-actions"><a href="?edit=<?= (int) $post['id'] ?>">EDIT</a><form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int) $post['id'] ?>"><input type="hidden" name="status" value="<?= $post['status'] === 'published' ? 'draft' : 'published' ?>"><button type="submit" name="action" value="toggle"><?= $post['status'] === 'published' ? 'UNPUBLISH' : 'PUBLISH' ?></button></form><form method="post" onsubmit="return confirm('Delete this post permanently?');"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int) $post['id'] ?>"><button type="submit" name="action" value="delete">DELETE</button></form></div></article><?php endforeach; ?>
</div></section></div></main><script src="../src/admin-news.js"></script></body></html>
