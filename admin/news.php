<?php

declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_admin();

$error = '';
$editing = ['id' => '', 'title' => '', 'content' => '', 'image' => null, 'status' => 'draft', 'published_at' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = (string) ($_POST['action'] ?? '');
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($action === 'delete' && $id) {
            $statement = db()->prepare('SELECT image FROM news WHERE id = :id');
            $statement->execute(['id' => $id]);
            $post = $statement->fetch();
            if ($post) {
                $statement = db()->prepare('DELETE FROM news WHERE id = :id');
                $statement->execute(['id' => $id]);
                delete_news_image($post['image']);
            }
            $_SESSION['flash'] = 'The news post was deleted.';
            header('Location: news.php');
            exit;
        }

        if (in_array($action, ['save', 'publish'], true)) {
            $title = trim((string) ($_POST['title'] ?? ''));
            $content = trim((string) ($_POST['content'] ?? ''));
            $status = $action === 'publish' ? 'published' : (string) ($_POST['status'] ?? 'draft');
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
                $statement = db()->prepare('SELECT image FROM news WHERE id = :id');
                $statement->execute(['id' => $id]);
                $oldImage = $statement->fetchColumn() ?: null;
            }
            $image = $oldImage;
            if (isset($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $image = store_news_image($_FILES['image']);
            }

            if ($id) {
                $statement = db()->prepare('UPDATE news SET title = :title, content = :content, image = :image, status = :status, published_at = :published_at WHERE id = :id');
                $statement->execute(compact('title', 'content', 'image', 'status', 'id') + ['published_at' => $publishedAt]);
            } else {
                $statement = db()->prepare('INSERT INTO news (title, content, image, status, published_at) VALUES (:title, :content, :image, :status, :published_at)');
                $statement->execute(compact('title', 'content', 'image', 'status') + ['published_at' => $publishedAt]);
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
        $statement = db()->prepare('SELECT * FROM news WHERE id = :id');
        $statement->execute(['id' => $editId]);
        $editing = $statement->fetch() ?: $editing;
    }
    $listStatement = db()->prepare('SELECT id, title, image, status, published_at, updated_at FROM news ORDER BY created_at DESC');
    $listStatement->execute();
    $posts = $listStatement->fetchAll();
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'The database request could not be completed.';
    $posts = $posts ?? [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $editing = [
            'id' => (string) ($_POST['id'] ?? ''), 'title' => (string) ($_POST['title'] ?? ''),
            'content' => (string) ($_POST['content'] ?? ''), 'image' => null,
            'status' => (string) ($_POST['status'] ?? 'draft'), 'published_at' => (string) ($_POST['published_at'] ?? ''),
        ];
    }
}
$publicationValue = !empty($editing['published_at']) ? date('Y-m-d\TH:i', strtotime((string) $editing['published_at'])) : date('Y-m-d\TH:i');
$flash = (string) ($_SESSION['flash'] ?? '');
unset($_SESSION['flash']);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Manage news — Laleh Barzegar</title><link rel="stylesheet" href="../styles/style.css"></head>
<body class="admin-page"><main class="admin-shell"><header class="admin-header"><div><p class="admin-eyebrow">Laleh Barzegar</p><h1>Manage news</h1></div><form action="logout.php" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><button class="text-button" type="submit">LOG OUT</button></form></header>
<?php if ($flash): ?><p class="admin-message" role="status"><?= h($flash) ?></p><?php endif; ?><?php if ($error): ?><p class="admin-message admin-error" role="alert"><?= h($error) ?></p><?php endif; ?>
<div class="admin-grid"><section><h2><?= $editing['id'] ? 'Edit post' : 'New post' ?></h2><form class="admin-form" method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="id" value="<?= h((string) $editing['id']) ?>">
<label>Title<input name="title" maxlength="255" value="<?= h($editing['title']) ?>" required></label>
<label>Text<textarea name="content" rows="14" required><?= h($editing['content']) ?></textarea></label>
<label>Image <span>(JPG, PNG or WEBP, max. 8 MB)</span><input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
<?php if ($editing['image']): ?><img class="admin-image-preview" src="../<?= h($editing['image']) ?>" alt="Current post image"><?php endif; ?>
<label>Status<select name="status"><option value="draft"<?= $editing['status'] === 'draft' ? ' selected' : '' ?>>Draft</option><option value="published"<?= $editing['status'] === 'published' ? ' selected' : '' ?>>Published</option></select></label>
<label>Publication date<input type="datetime-local" name="published_at" value="<?= h($publicationValue) ?>"></label>
<div class="admin-actions"><button type="submit" name="action" value="save">SAVE</button><button type="submit" name="action" value="publish">PUBLISH</button><?php if ($editing['id']): ?><a href="news.php">CANCEL</a><?php endif; ?></div>
</form></section><section><h2>Existing posts</h2><div class="admin-posts">
<?php if (!$posts): ?><p>No news posts have been created.</p><?php endif; ?>
<?php foreach ($posts as $post): ?><article class="admin-post"><div><p class="admin-post-meta"><?= h(ucfirst($post['status'])) ?> · <?= h($post['published_at'] ?: 'No publication date') ?></p><h3><?= h($post['title']) ?></h3></div><div class="admin-post-actions"><a href="?edit=<?= (int) $post['id'] ?>">EDIT</a><form method="post" onsubmit="return confirm('Diesen Beitrag wirklich löschen?');"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int) $post['id'] ?>"><button type="submit" name="action" value="delete">DELETE</button></form></div></article><?php endforeach; ?>
</div></section></div></main></body></html>
