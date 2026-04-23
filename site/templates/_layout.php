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
    <?php if (!empty($GLOBALS['admin_edit_path'])): ?>
    <input type="hidden" id="ie-csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
    <input type="hidden" id="ie-path" value="<?= htmlspecialchars($GLOBALS['admin_edit_path'], ENT_QUOTES) ?>">
    <div id="ie-toolbar" class="ie-toolbar">
        <button type="button" id="ie-toggle" class="ie-btn">Edit page</button>
        <button type="button" id="ie-save" class="ie-btn ie-btn--save" hidden>Save changes</button>
        <a href="/admin/" class="ie-btn">Admin &#x2197;</a>
    </div>
    <script src="/cms/inline-edit.js"></script>
    <?php else: ?>
    <div class="admin-front-bar">
        <a href="/admin/">Admin</a>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</body>
</html>
