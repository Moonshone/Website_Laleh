<?php

declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_admin();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Dashboard — Laleh Barzegar</title><link rel="stylesheet" href="../styles/style.css"></head>
<body class="admin-page"><main class="admin-shell"><header class="admin-header"><div><p class="admin-eyebrow">Laleh Barzegar</p><h1>Dashboard</h1><p>Signed in as <?= h((string) $_SESSION['username']) ?> (<?= h((string) $_SESSION['role']) ?>).</p></div><?php admin_navigation(); ?></header>
<section class="admin-dashboard"><h2>News CMS</h2><p>Create, edit, publish, unpublish, and delete articles without editing website files.</p><p><a class="admin-primary-link" href="news.php">MANAGE NEWS POSTS</a></p><?php if ($_SESSION['role'] === 'superadmin'): ?><p><a class="admin-primary-link" href="manage-admins.php">MANAGE ADMINISTRATORS</a></p><?php endif; ?></section></main></body></html>
