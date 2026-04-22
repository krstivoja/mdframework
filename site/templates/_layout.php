<?php
/**
 * Shared layout. Templates capture their content into $content_body and include this.
 * Usage in a template:
 *   ob_start(); ?> ...html... <?php $content_body = ob_get_clean();
 *   $page_title = 'My Title';
 *   require __DIR__ . '/_layout.php';
 */
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($page_title ?? 'Site') ?></title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <nav>
        <a href="/">Home</a>
        <a href="/blog">Blog</a>
        <a href="/about">About</a>
    </nav>
    <?= $content_body ?>

    <?php if (!empty($GLOBALS['admin_logged_in'])): ?>
    <div class="admin-front-bar">
        <?php if (!empty($GLOBALS['admin_edit_path'])): ?>
        <a href="/admin/edit?path=<?= urlencode($GLOBALS['admin_edit_path']) ?>">Edit page</a>
        <?php endif; ?>
        <a href="/admin/">Admin</a>
    </div>
    <?php endif; ?>
</body>
</html>
